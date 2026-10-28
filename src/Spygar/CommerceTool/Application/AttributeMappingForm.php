<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application;

use Doctrine\DBAL\Connection;
use Spygar\CommerceTool\Domain\Model\AttributeMapping;
use Spygar\CommerceTool\Domain\Model\StandardAttributeCatalog;

final class AttributeMappingForm
{
    private const IMAGE_CODE_PREFIX = 'image__';

    private static bool $columnsReady = false;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{fields: list<array<string, mixed>>, images: list<string>}
     */
    public function read(): array
    {
        $this->ensureColumns($this->connection);

        $rows = $this->connection->fetchAllAssociative(
            'SELECT commercetools_attribute, akeneo_attribute, variant_akeneo_attribute, mapping_type
             FROM spygar_commercetool_attribute_mapping
             ORDER BY position ASC, id ASC'
        );

        $catalog = StandardAttributeCatalog::byCode();
        $storedStandard = [];
        $extras = [];
        $images = [];

        foreach ($rows as $row) {
            if (AttributeMapping::TYPE_IMAGE === $row['mapping_type']) {
                if ('' !== (string) $row['akeneo_attribute']) {
                    $images[] = (string) $row['akeneo_attribute'];
                }
                continue;
            }

            $code = (string) $row['commercetools_attribute'];
            if (isset($catalog[$code])) {
                $storedStandard[$code] = $row;
                continue;
            }

            $extras[] = $row;
        }

        $fields = [];
        foreach (StandardAttributeCatalog::fields() as $definition) {
            $fields[] = $this->fieldPayload($definition, $storedStandard[$definition['code']] ?? null, false);
        }
        foreach ($extras as $row) {
            $code = (string) $row['commercetools_attribute'];
            $fields[] = $this->fieldPayload(
                ['code' => $code, 'label' => $this->humanize($code), 'required' => false],
                $row,
                true
            );
        }

        return ['fields' => $fields, 'images' => array_values(array_unique($images))];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    public function save(array $payload): array
    {
        $errors = $this->validate($payload);
        if ([] !== $errors) {
            return $errors;
        }

        $this->ensureColumns($this->connection);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $this->connection->transactional(function (Connection $connection) use ($payload, $now): void {
            $connection->executeStatement('DELETE FROM spygar_commercetool_attribute_mapping');

            $catalog = StandardAttributeCatalog::byCode();
            $position = 0;
            foreach ($payload['fields'] as $field) {
                $code = trim((string) $field['commercetools_attribute']);
                $akeneo = trim((string) ($field['akeneo_attribute'] ?? ''));
                $variant = trim((string) ($field['variant_akeneo_attribute'] ?? ''));
                $custom = !isset($catalog[$code]);
                if (!$custom && '' === $akeneo && '' === $variant) {
                    ++$position;
                    continue;
                }

                $connection->insert('spygar_commercetool_attribute_mapping', [
                    'code' => $code,
                    'akeneo_attribute' => $akeneo,
                    'commercetools_attribute' => $code,
                    'variant_akeneo_attribute' => '' === $variant ? null : $variant,
                    'mapping_type' => AttributeMapping::TYPE_FIELD,
                    'position' => $position,
                    'locale' => null,
                    'channel' => null,
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                ++$position;
            }

            $imagePosition = 0;
            foreach ($payload['images'] as $imageCode) {
                $connection->insert('spygar_commercetool_attribute_mapping', [
                    'code' => self::IMAGE_CODE_PREFIX.$imageCode,
                    'akeneo_attribute' => $imageCode,
                    'commercetools_attribute' => 'images',
                    'variant_akeneo_attribute' => null,
                    'mapping_type' => AttributeMapping::TYPE_IMAGE,
                    'position' => $imagePosition,
                    'locale' => null,
                    'channel' => null,
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                ++$imagePosition;
            }
        });

        return [];
    }

    private function ensureColumns(Connection $connection): void
    {
        if (self::$columnsReady) {
            return;
        }

        $present = array_map(
            'strtolower',
            $connection->fetchFirstColumn(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['spygar_commercetool_attribute_mapping']
            )
        );

        if (!in_array('variant_akeneo_attribute', $present, true)) {
            $connection->executeStatement('ALTER TABLE spygar_commercetool_attribute_mapping ADD variant_akeneo_attribute VARCHAR(255) DEFAULT NULL');
        }
        if (!in_array('mapping_type', $present, true)) {
            $connection->executeStatement("ALTER TABLE spygar_commercetool_attribute_mapping ADD mapping_type VARCHAR(20) NOT NULL DEFAULT 'field'");
        }
        if (!in_array('position', $present, true)) {
            $connection->executeStatement('ALTER TABLE spygar_commercetool_attribute_mapping ADD position INT NOT NULL DEFAULT 0');
        }

        self::$columnsReady = true;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function validate(array $payload): array
    {
        if (!isset($payload['fields']) || !is_array($payload['fields']) || !isset($payload['images']) || !is_array($payload['images'])) {
            return ['The mapping could not be read.'];
        }

        if (count($payload['fields']) > 200 || count($payload['images']) > 200) {
            return ['Too many mapping rows were submitted.'];
        }

        $errors = [];
        $seen = [];
        foreach ($payload['fields'] as $field) {
            if (!is_array($field)) {
                $errors[] = 'One mapping row is invalid.';
                continue;
            }

            $code = trim((string) ($field['commercetools_attribute'] ?? ''));
            if (1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]{0,99}$/', $code) || str_starts_with($code, self::IMAGE_CODE_PREFIX)) {
                $errors[] = sprintf('"%s" is not a valid CommerceTool attribute code.', $code);
                continue;
            }
            if (isset($seen[$code])) {
                $errors[] = sprintf('"%s" is mapped more than once.', $code);
                continue;
            }
            $seen[$code] = true;

            foreach (['akeneo_attribute', 'variant_akeneo_attribute'] as $property) {
                $value = trim((string) ($field[$property] ?? ''));
                if ('' !== $value && 1 !== preg_match('/^[A-Za-z0-9_]{1,255}$/', $value)) {
                    $errors[] = sprintf('The Akeneo attribute "%s" is not valid.', $value);
                }
            }
        }

        $seenImages = [];
        foreach ($payload['images'] as $imageCode) {
            $imageCode = trim((string) $imageCode);
            if (1 !== preg_match('/^[A-Za-z0-9_]{1,90}$/', $imageCode)) {
                $errors[] = sprintf('The image attribute "%s" is not valid.', $imageCode);
                continue;
            }
            if (isset($seenImages[$imageCode])) {
                $errors[] = sprintf('The image attribute "%s" is selected more than once.', $imageCode);
            }
            $seenImages[$imageCode] = true;
        }

        return $errors;
    }

    /**
     * @param array{code: string, label: string, required: bool} $definition
     * @param array<string, mixed>|null                          $row
     *
     * @return array<string, mixed>
     */
    private function fieldPayload(array $definition, ?array $row, bool $custom): array
    {
        return [
            'commercetools_attribute' => $definition['code'],
            'label' => $definition['label'],
            'required' => $definition['required'],
            'akeneo_attribute' => (string) ($row['akeneo_attribute'] ?? ''),
            'variant_akeneo_attribute' => (string) ($row['variant_akeneo_attribute'] ?? ''),
            'custom' => $custom,
        ];
    }

    private function humanize(string $code): string
    {
        $spaced = trim((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', str_replace('_', ' ', $code)));

        return ucfirst($spaced);
    }
}
