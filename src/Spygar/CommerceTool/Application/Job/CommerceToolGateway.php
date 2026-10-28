<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Spygar\CommerceTool\Application\CommerceToolClient;
use Spygar\CommerceTool\Domain\Model\Credential;

final class CommerceToolGateway
{
    public function __construct(private CommerceToolClient $client)
    {
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function get(Credential $credential, string $token, string $path, array $query = []): array
    {
        $options = [];
        if ([] !== $query) {
            $options['query'] = $query;
        }

        return $this->client->call($credential, $token, 'GET', $path, $options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function post(Credential $credential, string $token, string $path, array $options = []): array
    {
        return $this->client->call($credential, $token, 'POST', $path, $options);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOne(Credential $credential, string $token, string $path, array $query = []): ?array
    {
        $response = $this->get($credential, $token, $path, $query);
        if (200 === $response['status']) {
            return $response['body'];
        }
        if (404 === $response['status']) {
            return null;
        }

        throw new \RuntimeException($this->failure($credential, $response, 'CommerceTool could not be read.'));
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function pages(Credential $credential, string $token, string $path): \Generator
    {
        $lastId = null;
        $guard = 0;
        do {
            $query = ['limit' => 100, 'withTotal' => 'false', 'sort' => 'id asc'];
            if (null !== $lastId) {
                $query['where'] = sprintf('id > "%s"', $lastId);
            }
            $response = $this->get($credential, $token, $path, $query);
            if (200 !== $response['status']) {
                throw new \RuntimeException($this->failure($credential, $response, 'CommerceTool could not be read.'));
            }

            $results = $response['body']['results'] ?? [];
            if (!\is_array($results) || [] === $results) {
                return;
            }

            foreach ($results as $row) {
                if (\is_array($row)) {
                    yield $row;
                }
            }

            $last = $results[array_key_last($results)];
            $nextId = \is_array($last) ? ($last['id'] ?? null) : null;
            if (!\is_string($nextId) || '' === $nextId || $nextId === $lastId || str_contains($nextId, '"')) {
                return;
            }
            $lastId = $nextId;
            $count = \count($results);
            ++$guard;
        } while (100 === $count && $guard < 200);
    }

    /**
     * @param array{status?: int, body?: array<string, mixed>} $response
     */
    public function failure(Credential $credential, array $response, string $fallback): string
    {
        $body = $response['body'] ?? [];

        return $this->client->describe($credential, \is_array($body) ? $body : [], $fallback);
    }
}
