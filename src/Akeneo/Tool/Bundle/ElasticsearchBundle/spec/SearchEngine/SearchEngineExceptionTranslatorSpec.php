<?php

declare(strict_types=1);

namespace spec\Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine\SearchEngineExceptionTranslator;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ElasticsearchException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use OpenSearch\Common\Exceptions\BadRequest400Exception;
use OpenSearch\Common\Exceptions\Conflict409Exception;
use OpenSearch\Common\Exceptions\Missing404Exception;
use OpenSearch\Common\Exceptions\ServerErrorResponseException;
use PhpSpec\ObjectBehavior;

class SearchEngineExceptionTranslatorSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->shouldHaveType(SearchEngineExceptionTranslator::class);
    }

    public function it_translates_a_missing_404_into_a_client_response_exception(): void
    {
        $translated = SearchEngineExceptionTranslator::translate(
            new Missing404Exception('alias missing', 404)
        );

        self::assertExceptionType($translated, ClientResponseException::class);
        self::assertStatus($translated, 404);
        self::assertIsElasticsearchException($translated);
    }

    public function it_translates_a_conflict_409_into_a_client_response_exception(): void
    {
        $translated = SearchEngineExceptionTranslator::translate(
            new Conflict409Exception('version conflict', 409)
        );

        self::assertExceptionType($translated, ClientResponseException::class);
        self::assertStatus($translated, 409);
        self::assertIsElasticsearchException($translated);
    }

    public function it_translates_a_bad_request_400_into_a_client_response_exception(): void
    {
        $translated = SearchEngineExceptionTranslator::translate(
            new BadRequest400Exception('parsing error', 400)
        );

        self::assertExceptionType($translated, ClientResponseException::class);
        self::assertStatus($translated, 400);
        self::assertIsElasticsearchException($translated);
    }

    public function it_translates_a_server_error_into_a_server_response_exception(): void
    {
        $translated = SearchEngineExceptionTranslator::translate(
            new ServerErrorResponseException('upstream failed', 503)
        );

        self::assertExceptionType($translated, ServerResponseException::class);
        self::assertStatus($translated, 503);
        self::assertIsElasticsearchException($translated);
    }

    public function it_treats_a_zero_status_opensearch_exception_as_a_server_error(): void
    {
        // OpenSearch sometimes raises a generic OpenSearchException without a
        // status code (e.g. connection reset). The translator must default to
        // ServerResponseException so the caller can still react to "engine
        // unavailable".
        $translated = SearchEngineExceptionTranslator::translate(
            new ServerErrorResponseException('connection reset', 0)
        );

        self::assertExceptionType($translated, ServerResponseException::class);
        self::assertIsElasticsearchException($translated);
    }

    public function it_preserves_the_original_message(): void
    {
        $translated = SearchEngineExceptionTranslator::translate(
            new Missing404Exception('the specific alias is missing', 404)
        );

        if (!str_contains($translated->getMessage(), 'the specific alias is missing')) {
            throw new \RuntimeException(sprintf(
                'Expected message to contain the original text, got "%s"',
                $translated->getMessage()
            ));
        }
    }

    public function it_attaches_a_psr7_response_with_the_translated_status(): void
    {
        /** @var ClientResponseException $translated */
        $translated = SearchEngineExceptionTranslator::translate(
            new Missing404Exception('missing', 404)
        );

        $response = $translated->getResponse();
        if ($response->getStatusCode() !== 404) {
            throw new \RuntimeException(sprintf(
                'Expected PSR-7 response status 404, got %d',
                $response->getStatusCode()
            ));
        }
        if ((string) $response->getBody() === '') {
            throw new \RuntimeException('Expected PSR-7 response body to carry the OS message, but it was empty');
        }
    }

    public function it_chains_the_original_opensearch_exception_as_previous(): void
    {
        $original = new Missing404Exception('original', 404);
        $translated = SearchEngineExceptionTranslator::translate($original);

        if ($translated->getPrevious() !== $original) {
            throw new \RuntimeException('Expected the original OpenSearch exception to be set as previous');
        }
    }

    public function it_passes_through_non_opensearch_exceptions_unchanged(): void
    {
        $original = new \RuntimeException('not search engine related');
        $result = SearchEngineExceptionTranslator::translate($original);

        if ($result !== $original) {
            throw new \RuntimeException('Expected non-OS exception to pass through unchanged');
        }
    }

    public function it_passes_through_an_elasticsearch_exception_unchanged(): void
    {
        // If somehow an ES exception is fed back in (e.g. dual-engine code
        // path), the translator must not re-wrap it.
        $original = new ClientResponseException('already ES', 404);
        $result = SearchEngineExceptionTranslator::translate($original);

        if ($result !== $original) {
            throw new \RuntimeException('Expected ES exception to pass through unchanged');
        }
    }

    // --- assertion helpers (avoid relying on phpspec matchers for static calls) ---

    private static function assertExceptionType(\Throwable $actual, string $expected): void
    {
        if (!($actual instanceof $expected)) {
            throw new \RuntimeException(sprintf(
                'Expected %s, got %s',
                $expected,
                get_class($actual)
            ));
        }
    }

    private static function assertStatus(\Throwable $actual, int $expected): void
    {
        $status = null;
        if (method_exists($actual, 'getResponse')) {
            try {
                $status = $actual->getResponse()->getStatusCode();
            } catch (\Throwable) {
            }
        }
        $status ??= (int) $actual->getCode();

        if ($status !== $expected) {
            throw new \RuntimeException(sprintf('Expected status %d, got %d', $expected, $status));
        }
    }

    private static function assertIsElasticsearchException(\Throwable $actual): void
    {
        if (!($actual instanceof ElasticsearchException)) {
            throw new \RuntimeException(sprintf(
                'Expected %s to implement Elastic\\Elasticsearch\\Exception\\ElasticsearchException so existing `catch (ElasticsearchException $e)` blocks fire',
                get_class($actual)
            ));
        }
    }
}
