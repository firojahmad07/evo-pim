<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application;

use Doctrine\DBAL\Connection;

/**
 * Stores CommerceTool locales, currencies, channels, and their Akeneo mappings.
 * Kept outside the credential entity so the web process does not depend on cached ORM metadata.
 */
final class CredentialCatalogStore
{
    private const TABLE = 'spygar_commercetool_credential_catalog';

    private static bool $ready = false;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{
     *     catalog: array{locales: list<string>, currencies: list<string>, channels: list<array<string, mixed>>, fetched_at: ?string},
     *     locale_mapping: array<string, string>|\stdClass,
     *     currency_mapping: array<string, string>|\stdClass,
     *     channel_mapping: array<string, string>|\stdClass
     * }
     */
    public function present(string $code): array
    {
        $row = $this->find($code);
        if (null === $row) {
            return [
                'catalog' => [
                    'locales' => [],
                    'currencies' => [],
                    'channels' => [],
                    'fetched_at' => null,
                ],
                'locale_mapping' => new \stdClass(),
                'currency_mapping' => new \stdClass(),
                'channel_mapping' => new \stdClass(),
            ];
        }

        return [
            'catalog' => [
                'locales' => $row['locales'],
                'currencies' => $row['currencies'],
                'channels' => $row['channels'],
                'fetched_at' => $row['fetched_at'],
            ],
            'locale_mapping' => $this->asObject($row['locale_mapping']),
            'currency_mapping' => $this->asObject($row['currency_mapping']),
            'channel_mapping' => $this->asObject($row['channel_mapping']),
        ];
    }

    /**
     * @param array{
     *     locales: list<string>,
     *     currencies: list<string>,
     *     channels: list<array{id: string, key: string, label: string, roles: list<string>}>
     * } $fetched
     */
    public function saveFetched(string $code, array $fetched): void
    {
        $this->ensure();
        $current = $this->find($code);
        $locales = $fetched['locales'];
        $currencies = $fetched['currencies'];
        $channels = $fetched['channels'];
        $channelKeys = array_map(static fn (array $channel): string => $channel['key'], $channels);
        $localeMapping = $this->keep($current['locale_mapping'] ?? [], $locales);
        $currencyMapping = $this->keep($current['currency_mapping'] ?? [], $currencies);
        $channelMapping = $this->keep($current['channel_mapping'] ?? [], $channelKeys);
        $fetchedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->write($code, $locales, $currencies, $channels, $localeMapping, $currencyMapping, $channelMapping, $fetchedAt, null !== $current);
    }

    /**
     * @param array<string, mixed>|null $locales
     * @param array<string, mixed>|null $currencies
     * @param array<string, mixed>|null $channels
     */
    public function saveMappings(string $code, ?array $locales, ?array $currencies, ?array $channels): void
    {
        if (null === $locales && null === $currencies && null === $channels) {
            return;
        }

        $current = $this->find($code);
        if (null === $current) {
            return;
        }

        $channelKeys = array_map(static fn (array $channel): string => (string) ($channel['key'] ?? ''), $current['channels']);
        $localeMapping = null === $locales ? $current['locale_mapping'] : $this->keep($locales, $current['locales']);
        $currencyMapping = null === $currencies ? $current['currency_mapping'] : $this->keep($currencies, $current['currencies']);
        $channelMapping = null === $channels ? $current['channel_mapping'] : $this->keep($channels, $channelKeys);

        $this->write(
            $code,
            $current['locales'],
            $current['currencies'],
            $current['channels'],
            $localeMapping,
            $currencyMapping,
            $channelMapping,
            (string) $current['fetched_at'],
            true,
        );
    }

    public function delete(string $code): void
    {
        $this->ensure();
        $this->connection->delete(self::TABLE, ['credential_code' => $code]);
    }

    /**
     * @param list<string>                  $locales
     * @param list<string>                  $currencies
     * @param list<array<string, mixed>>    $channels
     * @param array<string, string>         $localeMapping
     * @param array<string, string>         $currencyMapping
     * @param array<string, string>         $channelMapping
     */
    private function write(
        string $code,
        array $locales,
        array $currencies,
        array $channels,
        array $localeMapping,
        array $currencyMapping,
        array $channelMapping,
        string $fetchedAt,
        bool $exists,
    ): void {
        $params = [
            'code' => $code,
            'locales' => $this->jsonList($locales),
            'currencies' => $this->jsonList($currencies),
            'channels' => $this->jsonList($channels),
            'locale_mapping' => $this->jsonMap($localeMapping),
            'currency_mapping' => $this->jsonMap($currencyMapping),
            'channel_mapping' => $this->jsonMap($channelMapping),
            'fetched_at' => '' === $fetchedAt ? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s') : $fetchedAt,
        ];

        if ($exists) {
            $this->connection->update(self::TABLE, [
                'locales' => $params['locales'],
                'currencies' => $params['currencies'],
                'channels' => $params['channels'],
                'locale_mapping' => $params['locale_mapping'],
                'currency_mapping' => $params['currency_mapping'],
                'channel_mapping' => $params['channel_mapping'],
                'fetched_at' => $params['fetched_at'],
            ], ['credential_code' => $code]);

            return;
        }

        $this->connection->insert(self::TABLE, [
            'credential_code' => $code,
            'locales' => $params['locales'],
            'currencies' => $params['currencies'],
            'channels' => $params['channels'],
            'locale_mapping' => $params['locale_mapping'],
            'currency_mapping' => $params['currency_mapping'],
            'channel_mapping' => $params['channel_mapping'],
            'fetched_at' => $params['fetched_at'],
        ]);
    }

    /**
     * @return array{
     *     locales: list<string>,
     *     currencies: list<string>,
     *     channels: list<array<string, mixed>>,
     *     locale_mapping: array<string, string>,
     *     currency_mapping: array<string, string>,
     *     channel_mapping: array<string, string>,
     *     fetched_at: ?string
     * }|null
     */
    private function find(string $code): ?array
    {
        $this->ensure();
        $row = $this->connection->fetchAssociative(
            'SELECT locales, currencies, channels, locale_mapping, currency_mapping, channel_mapping, fetched_at
             FROM '.self::TABLE.' WHERE credential_code = :code',
            ['code' => $code],
        );
        if (false === $row) {
            return null;
        }

        return [
            'locales' => $this->decodeList($row['locales']),
            'currencies' => $this->decodeList($row['currencies']),
            'channels' => $this->decodeChannels($row['channels']),
            'locale_mapping' => $this->decodeMap($row['locale_mapping']),
            'currency_mapping' => $this->decodeMap($row['currency_mapping']),
            'channel_mapping' => $this->decodeMap($row['channel_mapping']),
            'fetched_at' => isset($row['fetched_at']) ? (string) $row['fetched_at'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $mapping
     * @param list<string>         $allowed
     *
     * @return array<string, string>
     */
    private function keep(array $mapping, array $allowed): array
    {
        $allowed = array_fill_keys($allowed, true);
        $kept = [];
        foreach ($this->sanitizeMap($mapping) as $key => $value) {
            if (isset($allowed[$key])) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }

    /**
     * @return array<string, string>
     */
    private function sanitizeMap(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $key => $mapped) {
            $key = trim((string) $key);
            if (!\is_scalar($mapped)) {
                continue;
            }
            $mapped = trim((string) $mapped);
            if ('' === $key || '' === $mapped) {
                continue;
            }
            if (1 !== preg_match('/^[A-Za-z0-9_.:@-]{1,100}$/', $key) || 1 !== preg_match('/^[A-Za-z0-9_.:@-]{1,100}$/', $mapped)) {
                continue;
            }
            $clean[$key] = $mapped;
        }

        return $clean;
    }

    /**
     * @return list<string>
     */
    private function decodeList(mixed $value): array
    {
        $decoded = $this->decode($value);
        $list = [];
        foreach ($decoded as $item) {
            if (\is_string($item) && '' !== $item) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeChannels(mixed $value): array
    {
        $decoded = $this->decode($value);
        $channels = [];
        foreach ($decoded as $channel) {
            if (!\is_array($channel) || !isset($channel['key'])) {
                continue;
            }
            $channels[] = [
                'id' => (string) ($channel['id'] ?? ''),
                'key' => (string) $channel['key'],
                'label' => (string) ($channel['label'] ?? $channel['key']),
                'roles' => \is_array($channel['roles'] ?? null) ? array_values(array_map('strval', $channel['roles'])) : [],
            ];
        }

        return $channels;
    }

    /**
     * @return array<string, string>
     */
    private function decodeMap(mixed $value): array
    {
        return $this->sanitizeMap($this->decode($value));
    }

    /**
     * @return array<mixed>
     */
    private function decode(mixed $value): array
    {
        if (!\is_string($value) || '' === $value) {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, string> $map
     */
    private function asObject(array $map): array|\stdClass
    {
        return [] === $map ? new \stdClass() : $map;
    }

    /**
     * @param list<mixed> $value
     */
    private function jsonList(array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, string> $value
     */
    private function jsonMap(array $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_FORCE_OBJECT);
    }

    private function ensure(): void
    {
        if (self::$ready) {
            return;
        }

        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS spygar_commercetool_credential_catalog (
                credential_code VARCHAR(100) NOT NULL,
                locales JSON NOT NULL,
                currencies JSON NOT NULL,
                channels JSON NOT NULL,
                locale_mapping JSON NOT NULL,
                currency_mapping JSON NOT NULL,
                channel_mapping JSON NOT NULL,
                fetched_at DATETIME NOT NULL,
                PRIMARY KEY (credential_code)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        self::$ready = true;
    }
}
