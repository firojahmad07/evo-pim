<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use Elastic\Elasticsearch\ClientBuilder as ElasticsearchClientBuilder;

/**
 * Returns the search-engine client builder selected by the `SEARCH_ENGINE`
 * environment variable.
 *
 * - `elasticsearch` (default) -> native Elasticsearch v8 client builder
 * - `opensearch`              -> OpenSearch client wrapped in an adapter that
 *                                exposes the same surface as the Elasticsearch
 *                                client (see {@see OpenSearchClientAdapter})
 *
 * Both returned builders expose `setHosts(array $hosts)` and `build()`, so the
 * downstream services can stay engine-agnostic.
 */
final class SearchEngineClientBuilderFactory
{
    public const ENGINE_ELASTICSEARCH = 'elasticsearch';
    public const ENGINE_OPENSEARCH = 'opensearch';

    public static function createBuilder(string $searchEngine = self::ENGINE_ELASTICSEARCH): object
    {
        return match (strtolower(trim($searchEngine))) {
            self::ENGINE_OPENSEARCH => new OpenSearchClientBuilderAdapter(),
            default => ElasticsearchClientBuilder::create(),
        };
    }
}
