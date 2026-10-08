<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use OpenSearch\ClientBuilder as OpenSearchClientBuilder;

/**
 * Mimics the Elasticsearch `ClientBuilder` API (`setHosts()` + `build()`) but
 * produces an OpenSearch client wrapped in {@see OpenSearchClientAdapter}.
 *
 * This lets services that depend on `akeneo_elasticsearch.client_builder` keep
 * building their client the same way regardless of which engine is selected.
 */
final class OpenSearchClientBuilderAdapter
{
    private OpenSearchClientBuilder $builder;

    public function __construct()
    {
        $this->builder = OpenSearchClientBuilder::create();
    }

    public function setHosts(array $hosts): self
    {
        $this->builder->setHosts($hosts);

        return $this;
    }

    public function build(): OpenSearchClientAdapter
    {
        return new OpenSearchClientAdapter($this->builder->build());
    }
}
