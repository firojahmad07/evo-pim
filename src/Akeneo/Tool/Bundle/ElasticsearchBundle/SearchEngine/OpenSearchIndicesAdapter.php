<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use OpenSearch\Namespaces\IndicesNamespace;

/**
 * Forwards `->indices()->*` calls to the OpenSearch indices namespace and
 * wraps the array results in {@see SearchEngineResponse} so callers using
 * `->asArray()` continue to work.
 */
final class OpenSearchIndicesAdapter
{
    public function __construct(private IndicesNamespace $indices)
    {
    }

    public function __call(string $name, array $arguments): mixed
    {
        try {
            $result = $this->indices->$name(...$arguments);
        } catch (\Throwable $e) {
            throw SearchEngineExceptionTranslator::translate($e);
        }

        // OpenSearch `exists*` methods return a plain bool — pass it through
        // so existing truthy-checks like `if ($indices->existsAlias(...))` work.
        if (is_bool($result)) {
            return $result;
        }

        return new SearchEngineResponse(is_array($result) ? $result : (array) $result);
    }
}
