<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use OpenSearch\Client as OpenSearchClient;

/**
 * Adapts the OpenSearch PHP client so it presents the same surface that the
 * Elasticsearch PHP client v8 exposes to LibrePIM. Top-level calls forward
 * via {@see __call()} and return plain arrays (which `Client::toResultArray()`
 * already accepts) or booleans for `exists*` methods. Every failure is
 * re-thrown as an Elasticsearch exception type via
 * {@see SearchEngineExceptionTranslator}.
 */
final class OpenSearchClientAdapter
{
    public function __construct(private OpenSearchClient $client)
    {
    }

    public function indices(): OpenSearchIndicesAdapter
    {
        return new OpenSearchIndicesAdapter($this->client->indices());
    }

    public function cat(): OpenSearchCatAdapter
    {
        return new OpenSearchCatAdapter($this->client->cat());
    }

    public function __call(string $name, array $arguments): mixed
    {
        try {
            $result = $this->client->$name(...$arguments);
        } catch (\Throwable $e) {
            throw SearchEngineExceptionTranslator::translate($e);
        }

        // `exists*` style methods may return a plain bool — pass through.
        if (is_bool($result)) {
            return $result;
        }

        return is_array($result) ? $result : (array) $result;
    }
}
