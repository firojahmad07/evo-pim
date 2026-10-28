<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Spygar\CommerceTool\Domain\Model\Credential;

/**
 * Loads a CommerceTool project's locales, currencies, and channels.
 * The client secret is sent only as HTTP basic authentication and is never logged.
 */
final class CommerceToolClient
{
    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client([
            'timeout' => 12,
            'connect_timeout' => 5,
            'http_errors' => false,
            'allow_redirects' => false,
        ]);
    }

    /**
     * @return array{
     *     locales: list<string>,
     *     currencies: list<string>,
     *     channels: list<array{id: string, key: string, label: string, roles: list<string>}>
     * }
     */
    public function fetch(Credential $credential): array
    {
        [$authUrl, $apiUrl] = $this->endpoints($credential);
        $token = $this->token($credential, $authUrl);
        $project = $this->project($credential, $apiUrl, $token);
        $locales = $this->codes($project['languages'] ?? []);
        $currencies = $this->codes($project['currencies'] ?? []);

        return [
            'locales' => $locales,
            'currencies' => $currencies,
            'channels' => $this->channels($credential, $apiUrl, $token, $locales[0] ?? ''),
        ];
    }

    /**
     * @return array{api_url: string, token: string, locales: list<string>, currencies: list<string>}
     */
    public function connect(Credential $credential): array
    {
        [$authUrl, $apiUrl] = $this->endpoints($credential);
        $token = $this->token($credential, $authUrl);
        $project = $this->project($credential, $apiUrl, $token);

        return [
            'api_url' => $apiUrl,
            'token' => $token,
            'locales' => $this->codes($project['languages'] ?? []),
            'currencies' => $this->codes($project['currencies'] ?? []),
        ];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function call(Credential $credential, string $token, string $method, string $path, array $options = []): array
    {
        [, $apiUrl] = $this->endpoints($credential);
        $url = $apiUrl.'/'.rawurlencode($credential->getProjectKey()).'/'.ltrim($path, '/');
        $headers = $this->bearer($token);
        if (isset($options['json'])) {
            $headers['Content-Type'] = 'application/json';
        }
        $options['headers'] = array_merge($headers, $options['headers'] ?? []);
        $response = $this->request($method, $url, $options, $credential, 'api_url');

        return [
            'status' => $response->getStatusCode(),
            'body' => $this->decode($response),
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    public function describe(Credential $credential, array $body, string $fallback): string
    {
        return $this->withDetail($fallback, $body, $credential);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function endpoints(Credential $credential): array
    {
        $auth = trim((string) $credential->getAuthUrl());
        $api = trim((string) $credential->getApiUrl());
        if ('' !== $auth || '' !== $api) {
            if ('' === $auth || '' === $api) {
                throw new CommerceToolConnectionException(
                    'Enter both an auth URL and an API URL, or use a region and leave both URLs empty.',
                    '' === $auth ? 'auth_url' : 'api_url',
                );
            }

            return [$this->httpsBase($auth, 'auth_url'), $this->httpsBase($api, 'api_url')];
        }

        $region = strtolower(trim((string) $credential->getRegion()));
        if (1 !== preg_match('/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/', $region) || str_contains($region, '..')) {
            throw new CommerceToolConnectionException(
                'Enter a region such as europe-west1.gcp, or provide both an auth URL and an API URL.',
                'region',
            );
        }

        return [
            'https://auth.'.$region.'.commercetools.com',
            'https://api.'.$region.'.commercetools.com',
        ];
    }

    private function httpsBase(string $url, string $field): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        if (
            !\is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ('/' !== $path && '' !== $path)
        ) {
            throw new CommerceToolConnectionException(
                'Use an https host URL without a path, query, username, or password.',
                $field,
            );
        }

        return 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function token(Credential $credential, string $authUrl): string
    {
        $projectKey = $credential->getProjectKey();
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,255}$/', $projectKey)) {
            throw new CommerceToolConnectionException(
                'The project key can contain only letters, numbers, hyphens, and underscores.',
                'project_key',
            );
        }

        $form = ['grant_type' => 'client_credentials'];
        $scope = trim((string) $credential->getScope());
        if ('' !== $scope) {
            $form['scope'] = $scope;
        }

        $response = $this->request('POST', $authUrl.'/oauth/token', [
            'auth' => [$credential->getClientId(), $credential->getClientSecret()],
            'form_params' => $form,
            'headers' => ['Accept' => 'application/json'],
        ], $credential, $this->endpointField($credential));

        $status = $response->getStatusCode();
        $body = $this->decode($response);
        if (200 === $status && isset($body['access_token']) && \is_string($body['access_token']) && '' !== $body['access_token']) {
            return $body['access_token'];
        }

        if (400 === $status && ($body['error'] ?? '') === 'invalid_scope') {
            throw new CommerceToolConnectionException(
                'CommerceTool rejected the OAuth scope. Leave the scope empty to use the API client defaults, or enter the scopes granted to that client.',
                '' === $scope ? 'client_id' : 'scope',
            );
        }

        $field = \in_array($status, [400, 401], true) ? 'client_secret' : $this->endpointField($credential);
        $fallback = \in_array($status, [400, 401], true)
            ? 'CommerceTool rejected the client ID or client secret.'
            : 'CommerceTool could not be reached.';

        throw new CommerceToolConnectionException($this->withDetail($fallback, $body, $credential), $field);
    }

    /**
     * @return array<string, mixed>
     */
    private function project(Credential $credential, string $apiUrl, string $token): array
    {
        $response = $this->request('GET', $apiUrl.'/'.rawurlencode($credential->getProjectKey()), [
            'headers' => $this->bearer($token),
        ], $credential, $this->endpointField($credential));

        $status = $response->getStatusCode();
        $body = $this->decode($response);
        if (200 === $status) {
            return $body;
        }

        $field = 404 === $status ? 'project_key' : 'client_id';
        $fallback = match ($status) {
            404 => 'CommerceTool could not find that project key.',
            401 => 'CommerceTool rejected the client ID or client secret.',
            403 => 'The API client needs permission to read the project.',
            default => 'CommerceTool could not be reached.',
        };

        throw new CommerceToolConnectionException($this->withDetail($fallback, $body, $credential), $field);
    }

    /**
     * @return list<array{id: string, key: string, label: string, roles: list<string>}>
     */
    private function channels(Credential $credential, string $apiUrl, string $token, string $preferredLocale): array
    {
        $channels = [];
        $offset = 0;
        $limit = 200;

        do {
            $response = $this->request('GET', $apiUrl.'/'.rawurlencode($credential->getProjectKey()).'/channels', [
                'headers' => $this->bearer($token),
                'query' => ['limit' => $limit, 'offset' => $offset, 'sort' => 'id asc'],
            ], $credential, $this->endpointField($credential));

            $status = $response->getStatusCode();
            $body = $this->decode($response);
            if (200 !== $status) {
                $fallback = 403 === $status
                    ? 'The API client needs permission to read channels.'
                    : 'CommerceTool could not be reached while loading channels.';
                throw new CommerceToolConnectionException($this->withDetail($fallback, $body, $credential), 'client_id');
            }

            $results = $body['results'] ?? [];
            if (!\is_array($results)) {
                break;
            }

            foreach ($results as $channel) {
                if (!\is_array($channel)) {
                    continue;
                }
                $normalized = $this->channel($channel, $preferredLocale);
                if (null !== $normalized) {
                    $channels[$normalized['key']] = $normalized;
                }
            }

            $count = \count($results);
            $offset += $count;
        } while ($count === $limit && $offset < 1000);

        $channels = array_values($channels);
        usort($channels, static fn (array $left, array $right): int => strcmp($left['key'], $right['key']));

        return $channels;
    }

    /**
     * @param array<string, mixed> $channel
     *
     * @return array{id: string, key: string, label: string, roles: list<string>}|null
     */
    private function channel(array $channel, string $preferredLocale): ?array
    {
        $id = trim((string) ($channel['id'] ?? ''));
        $key = trim((string) ($channel['key'] ?? ''));
        if ('' === $key) {
            $key = $id;
        }
        if ('' === $key || 1 !== preg_match('/^[A-Za-z0-9_.:@-]{1,100}$/', $key)) {
            return null;
        }

        $roles = [];
        foreach ($channel['roles'] ?? [] as $role) {
            if (\is_string($role) && '' !== trim($role)) {
                $roles[] = trim($role);
            }
        }

        return [
            'id' => $id,
            'key' => $key,
            'label' => $this->label($channel['name'] ?? null, $preferredLocale, $key),
            'roles' => $roles,
        ];
    }

    private function label(mixed $name, string $preferredLocale, string $fallback): string
    {
        if (\is_string($name) && '' !== trim($name)) {
            return trim($name);
        }
        if (\is_array($name)) {
            if (isset($name[$preferredLocale]) && \is_string($name[$preferredLocale]) && '' !== trim($name[$preferredLocale])) {
                return trim($name[$preferredLocale]);
            }
            foreach ($name as $value) {
                if (\is_string($value) && '' !== trim($value)) {
                    return trim($value);
                }
            }
        }

        return $fallback;
    }

    /**
     * @return list<string>
     */
    private function codes(mixed $values): array
    {
        if (!\is_array($values)) {
            return [];
        }

        $codes = [];
        foreach ($values as $value) {
            if (!\is_string($value)) {
                continue;
            }
            $value = trim($value);
            if (1 === preg_match('/^[A-Za-z0-9_.:@-]{1,100}$/', $value)) {
                $codes[$value] = $value;
            }
        }

        $codes = array_values($codes);
        sort($codes);

        return $codes;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $url, array $options, Credential $credential, string $field): ResponseInterface
    {
        try {
            return $this->http->request($method, $url, $options);
        } catch (GuzzleException) {
            throw new CommerceToolConnectionException(
                'CommerceTool could not be reached. Check the region, or the auth and API URLs.',
                $field,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        if ('' === $body) {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function withDetail(string $fallback, array $body, Credential $credential): string
    {
        $detail = '';
        if (isset($body['error_description']) && \is_string($body['error_description'])) {
            $detail = $body['error_description'];
        } elseif (isset($body['message']) && \is_string($body['message'])) {
            $detail = $body['message'];
        }

        $detail = $this->redact($detail, $credential);
        if ('' === $detail) {
            return $fallback;
        }

        return rtrim($fallback, '.').': '.$detail;
    }

    private function redact(string $message, Credential $credential): string
    {
        foreach ([$credential->getClientSecret(), $credential->getClientId()] as $secret) {
            if (\strlen($secret) >= 4) {
                $message = str_replace($secret, '***', $message);
            }
        }

        $message = trim((string) preg_replace('/\s+/', ' ', $message));

        return mb_substr($message, 0, 180);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }

    private function endpointField(Credential $credential): string
    {
        $auth = trim((string) $credential->getAuthUrl());
        $api = trim((string) $credential->getApiUrl());

        return '' !== $auth || '' !== $api ? 'api_url' : 'region';
    }
}
