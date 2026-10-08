<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use OpenSearch\Common\Exceptions\OpenSearchException;

final class SearchEngineExceptionTranslator
{
    public static function translate(\Throwable $e): \Throwable
    {
        if (!$e instanceof OpenSearchException) {
            return $e;
        }

        $status = (int) $e->getCode();
        $body = $e->getMessage();
        $isServerError = $status <= 0 || $status >= 500;
        $responseStatus = $status > 0 ? $status : 500;

        $exception = $isServerError
            ? new ServerResponseException($body, $status, $e)
            : new ClientResponseException($body, $status, $e);

        $exception->setResponse(new GuzzleResponse($responseStatus, [], $body));

        return $exception;
    }
}
