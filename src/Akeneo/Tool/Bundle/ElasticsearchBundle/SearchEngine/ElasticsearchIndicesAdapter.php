<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use Elastic\Elasticsearch\Endpoints\Indices;
use Elastic\Elasticsearch\Response\Elasticsearch;

/**
 * Adapts the Elasticsearch "indices" namespace so it behaves like the
 * OpenSearch indices namespace: methods return plain arrays (or bools for the
 * `exists*` calls) and any failure is translated to an OpenSearch exception.
 *
 * @method array create(array $params)
 * @method array delete(array $params)
 * @method array refresh(array $params)
 * @method array get(array $params)
 * @method array getAlias(array $params)
 * @method array getSettings(array $params)
 * @method array putSettings(array $params)
 * @method array updateAliases(array $params)
 */
final class ElasticsearchIndicesAdapter
{
    public function __construct(private Indices $indices)
    {
    }

    public function exists(array $params): bool
    {
        return (bool) $this->call('exists', $params, true);
    }

    public function existsAlias(array $params): bool
    {
        return (bool) $this->call('existsAlias', $params, true);
    }

    public function __call(string $name, array $arguments): array
    {
        return (array) $this->call($name, $arguments[0] ?? [], false);
    }

    private function call(string $name, array $params, bool $asBool): bool|array
    {
        try {
            $response = $this->indices->$name($params);
        } catch (\Throwable $e) {
            throw SearchEngineExceptionTranslator::translate($e);
        }

        if ($response instanceof Elasticsearch) {
            return $asBool ? $response->asBool() : $response->asArray();
        }

        return $asBool ? (bool) $response : (array) $response;
    }
}
