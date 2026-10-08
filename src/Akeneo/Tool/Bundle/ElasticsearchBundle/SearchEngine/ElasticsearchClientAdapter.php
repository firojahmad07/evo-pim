<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;

/**
 * Adapts the Elasticsearch 8 PHP client so it presents the same surface as the
 * OpenSearch PHP client used throughout LibrePIM: every call returns a plain
 * array and every failure throws an OpenSearch exception type.
 *
 * This is the bridge that lets LibrePIM run on Elasticsearch when
 * SEARCH_ENGINE=elasticsearch, while the rest of the codebase keeps talking
 * to a single OpenSearch-shaped client API.
 *
 * @method array index(array $params)
 * @method array bulk(array $params)
 * @method array get(array $params)
 * @method array search(array $params)
 * @method array msearch(array $params)
 * @method array count(array $params)
 * @method array delete(array $params)
 * @method array deleteByQuery(array $params)
 * @method array updateByQuery(array $params)
 * @method array reindex(array $params)
 */
final class ElasticsearchClientAdapter
{
    public function __construct(private Client $client)
    {
    }

    public function indices(): ElasticsearchIndicesAdapter
    {
        return new ElasticsearchIndicesAdapter($this->client->indices());
    }

    public function cat(): ElasticsearchCatAdapter
    {
        return new ElasticsearchCatAdapter($this->client->cat());
    }

    public function __call(string $name, array $arguments): array
    {
        try {
            $response = $this->client->$name(...$arguments);
        } catch (\Throwable $e) {
            throw SearchEngineExceptionTranslator::translate($e);
        }

        if ($response instanceof Elasticsearch) {
            return $response->asArray();
        }

        return (array) $response;
    }
}
