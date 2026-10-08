<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use Elastic\Elasticsearch\Endpoints\Cat;
use Elastic\Elasticsearch\Response\Elasticsearch;

/**
 * Adapts the Elasticsearch "cat" namespace so it behaves like the OpenSearch
 * cat namespace: methods return plain arrays and failures are translated to
 * OpenSearch exceptions.
 *
 * @method array aliases(array $params = [])
 * @method array indices(array $params = [])
 */
final class ElasticsearchCatAdapter
{
    public function __construct(private Cat $cat)
    {
    }

    public function __call(string $name, array $arguments): array
    {
        try {
            $response = $this->cat->$name($arguments[0] ?? []);
        } catch (\Throwable $e) {
            throw SearchEngineExceptionTranslator::translate($e);
        }

        if ($response instanceof Elasticsearch) {
            return $response->asArray();
        }

        return (array) $response;
    }
}
