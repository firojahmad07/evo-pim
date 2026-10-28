<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Doctrine\DBAL\Connection;

final class MappingReader
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<array{akeneo: string, commercetool: string, variant: ?string, type: string, locale: ?string, channel: ?string}>
     */
    public function active(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT akeneo_attribute, commercetools_attribute, variant_akeneo_attribute, mapping_type, locale, channel
                 FROM spygar_commercetool_attribute_mapping
                 WHERE active = 1
                 ORDER BY position ASC, id ASC'
            );
        } catch (\Throwable) {
            return [];
        }

        $mappings = [];
        foreach ($rows as $row) {
            $akeneo = trim((string) ($row['akeneo_attribute'] ?? ''));
            $commercetool = trim((string) ($row['commercetools_attribute'] ?? ''));
            if ('' === $akeneo || '' === $commercetool) {
                continue;
            }
            $variant = trim((string) ($row['variant_akeneo_attribute'] ?? ''));
            $locale = trim((string) ($row['locale'] ?? ''));
            $channel = trim((string) ($row['channel'] ?? ''));
            $mappings[] = [
                'akeneo' => $akeneo,
                'commercetool' => $commercetool,
                'variant' => '' !== $variant ? $variant : null,
                'type' => trim((string) ($row['mapping_type'] ?? 'field')),
                'locale' => '' !== $locale ? $locale : null,
                'channel' => '' !== $channel ? $channel : null,
            ];
        }

        return $mappings;
    }
}
