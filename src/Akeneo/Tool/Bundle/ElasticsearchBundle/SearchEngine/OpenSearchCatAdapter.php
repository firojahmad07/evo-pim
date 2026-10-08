<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

use OpenSearch\Namespaces\CatNamespace;

/**
 * Forwards `->cat()->*` calls to the OpenSearch cat namespace and wraps the
 * array results in {@see SearchEngineResponse}.
 */
final class OpenSearchCatAdapter
{
    public function __construct(private CatNamespace $cat)
    {
    }

    public function __call(string $name, array $arguments): mixed
    {
        try {
            $result = $this->cat->$name(...$arguments);
        } catch (\Throwable $e) {
            throw SearchEngineExceptionTranslator::translate($e);
        }

        if (is_bool($result)) {
            return $result;
        }

        return new SearchEngineResponse(is_array($result) ? $result : (array) $result);
    }
}
