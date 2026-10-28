<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Domain\Model;

final class StandardAttributeCatalog
{
    /**
     * @return list<array{code: string, label: string, required: bool}>
     */
    public static function fields(): array
    {
        return [
            ['code' => 'sku', 'label' => 'SKU', 'required' => false],
            ['code' => 'name', 'label' => 'Name', 'required' => true],
            ['code' => 'slug', 'label' => 'Slug', 'required' => false],
            ['code' => 'description', 'label' => 'Description', 'required' => false],
            ['code' => 'price', 'label' => 'Price', 'required' => true],
            ['code' => 'key', 'label' => 'Key', 'required' => false],
            ['code' => 'quantity', 'label' => 'Quantity', 'required' => false],
            ['code' => 'taxCategory', 'label' => 'Tax category', 'required' => false],
            ['code' => 'categories', 'label' => 'Categories', 'required' => false],
            ['code' => 'metaTitle', 'label' => 'Meta title', 'required' => false],
            ['code' => 'metaDescription', 'label' => 'Meta description', 'required' => false],
            ['code' => 'searchKeywords', 'label' => 'Search keywords', 'required' => false],
        ];
    }

    /**
     * @return array<string, array{code: string, label: string, required: bool}>
     */
    public static function byCode(): array
    {
        $indexed = [];
        foreach (self::fields() as $field) {
            $indexed[$field['code']] = $field;
        }

        return $indexed;
    }
}
