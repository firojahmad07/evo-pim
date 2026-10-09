<?php

declare(strict_types=1);

namespace Akeneo\Pim\Enrichment\Bundle\Storage\Sql\Category;

use Doctrine\DBAL\Connection;

/**
 * Finds categories inside one tree whose code or label matches a search string.
 */
final class SearchCategoriesInTree
{
    private const MAX_RESULTS = 100;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<array{id: int, code: string, label: string, path: string}>
     */
    public function search(int $treeId, string $localeCode, string $search): array
    {
        $search = trim($search);
        if ('' === $search) {
            return [];
        }

        $like = '%'.addcslashes($search, '%_\\').'%';

        $sql = <<<SQL
SELECT
    category.id,
    category.code,
    COALESCE(NULLIF(translation.label, ''), CONCAT('[', category.code, ']')) AS label,
    (
        SELECT GROUP_CONCAT(
            COALESCE(NULLIF(ancestor_translation.label, ''), CONCAT('[', ancestor.code, ']'))
            ORDER BY ancestor.lft
            SEPARATOR ' / '
        )
        FROM pim_catalog_category ancestor
        LEFT JOIN pim_catalog_category_translation ancestor_translation
            ON ancestor_translation.foreign_key = ancestor.id
            AND ancestor_translation.locale = :locale
        WHERE ancestor.root = category.root
          AND ancestor.lft < category.lft
          AND ancestor.rgt > category.rgt
    ) AS path
FROM pim_catalog_category category
INNER JOIN pim_catalog_category tree
    ON tree.id = :treeId
    AND tree.parent_id IS NULL
LEFT JOIN pim_catalog_category_translation translation
    ON translation.foreign_key = category.id
    AND translation.locale = :locale
WHERE category.root = tree.id
  AND (
      category.code LIKE :search
      OR translation.label LIKE :search
  )
ORDER BY category.lft
LIMIT :limit
SQL;

        $rows = $this->connection->executeQuery(
            $sql,
            [
                'treeId' => $treeId,
                'locale' => $localeCode,
                'search' => $like,
                'limit' => self::MAX_RESULTS,
            ],
            [
                'treeId' => \PDO::PARAM_INT,
                'limit' => \PDO::PARAM_INT,
            ]
        )->fetchAllAssociative();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'label' => (string) $row['label'],
            'path' => (string) ($row['path'] ?? ''),
        ], $rows);
    }
}
