<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use Elastic\Elasticsearch\ClientBuilder;

/**
 * A client builder that mimics the OpenSearch client builder API
 * (`setHosts()` / `build()`) but produces an Elasticsearch client wrapped in
 * {@see ElasticsearchClientAdapter}.
 *
 * This lets LibrePIM's services keep building their client the same way
 * regardless of the configured search engine.
 */
final class ElasticsearchClientBuilderAdapter
{
    private ClientBuilder $builder;

    public function __construct()
    {
        $this->builder = ClientBuilder::create();
    }

    public function setHosts(array $hosts): self
    {
        $this->builder->setHosts($hosts);

        return $this;
    }

    public function build(): ElasticsearchClientAdapter
    {
        return new ElasticsearchClientAdapter($this->builder->build());
    }
}
