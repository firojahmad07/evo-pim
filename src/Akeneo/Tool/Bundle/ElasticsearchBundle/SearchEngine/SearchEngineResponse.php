<?php

declare(strict_types=1);

namespace Akeneo\Tool\Bundle\ElasticsearchBundle\SearchEngine;

/**
 * Lightweight array wrapper used by the OpenSearch adapters so callers that
 * expect an Elasticsearch-style response (`->asArray()` + array access) work
 * unchanged when the OpenSearch client (which returns plain arrays) is in use.
 */
final class SearchEngineResponse implements \ArrayAccess, \IteratorAggregate, \Countable
{
    public function __construct(private array $data)
    {
    }

    public function asArray(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }
}
