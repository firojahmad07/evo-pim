<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Akeneo\Pim\Enrichment\Component\Product\Model\EntityWithValuesInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ValueInterface;
use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeRepository;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;

final class ProductPayload
{
    private const FIELDS = [
        'name' => 'name',
        'slug' => 'slug',
        'description' => 'description',
        'metatitle' => 'metaTitle',
        'metadescription' => 'metaDescription',
        'searchkeywords' => 'searchKeywords',
        'price' => 'price',
        'categories' => 'categories',
        'sku' => 'sku',
        'key' => 'key',
        'quantity' => 'quantity',
        'taxcategory' => 'taxCategory',
    ];

    /** @var array<string, AttributeInterface>|null */
    private ?array $attributeCache = null;

    public function __construct(private AttributeRepository $attributes)
    {
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array{
     *     name: array<string, string>,
     *     slug: array<string, string>,
     *     description: array<string, string>,
     *     metaTitle: array<string, string>,
     *     metaDescription: array<string, string>,
     *     prices: list<array<string, mixed>>,
     *     attributes: list<array{name: string, value: mixed}>,
     *     categories: list<string>,
     *     notices: array<string, string>
     * }
     */
    public function collect(
        TransferContext $context,
        EntityWithValuesInterface $entity,
        array $definitions,
        string $fallback,
        bool $variantLevel,
    ): array {
        $draft = [
            'name' => [],
            'slug' => [],
            'description' => [],
            'metaTitle' => [],
            'metaDescription' => [],
            'prices' => [],
            'attributes' => [],
            'categories' => $this->categories($entity),
            'notices' => [],
        ];
        $attributes = [];
        foreach ($this->attributeMap() as $attribute) {
            $attributes[$attribute->getCode()] = $attribute;
        }

        foreach ($context->mappings as $mapping) {
            if ('image' === $mapping['type']) {
                $draft['notices'][$mapping['commercetool']] = 'Image mappings are not sent by this job.';
                continue;
            }
            $akeneoCode = $variantLevel && null !== $mapping['variant'] ? $mapping['variant'] : $mapping['akeneo'];
            $attribute = $attributes[$akeneoCode] ?? null;
            if (!$attribute instanceof AttributeInterface) {
                $draft['notices'][$akeneoCode] = sprintf('Akeneo attribute %s does not exist.', $akeneoCode);
                continue;
            }
            $field = self::FIELDS[strtolower($mapping['commercetool'])] ?? null;
            if (\in_array($field, ['quantity', 'taxCategory', 'searchKeywords'], true)) {
                $draft['notices'][$field] = sprintf('CommerceTool field %s is not synchronized.', $field);
                continue;
            }
            if (\in_array($field, ['sku', 'key', 'categories'], true)) {
                continue;
            }

            $scope = $attribute->isScopable() ? ($mapping['channel'] ?? $context->scope()) : null;
            if ($attribute->isScopable() && null === $scope) {
                $draft['notices'][$attribute->getCode()] = sprintf('Map an Akeneo channel before exporting scopable attribute %s.', $attribute->getCode());
                continue;
            }
            $locales = $this->locales($context, $attribute, $mapping['locale']);
            if ($attribute->isLocalizable() && [] === $locales) {
                $draft['notices'][$attribute->getCode()] = sprintf('Map a CommerceTool locale before exporting %s.', $attribute->getCode());
                continue;
            }

            if ('price' === $field) {
                $draft['prices'] = $this->prices($context, $entity, $attribute, $scope);
                continue;
            }
            if (\in_array($field, ['name', 'slug', 'description', 'metaTitle', 'metaDescription'], true)) {
                $draft[$field] = $this->localizedText($context, $entity, $attribute, $locales, $scope, $fallback, 'slug' === $field);
                continue;
            }

            $definition = $definitions[$mapping['commercetool']] ?? null;
            if (!\is_array($definition)) {
                $draft['notices'][$mapping['commercetool']] = sprintf('Attribute %s is not on the CommerceTool product type. Run the attribute export first.', $mapping['commercetool']);
                continue;
            }
            $value = $this->attributeValue($context, $entity, $attribute, $definition, $locales, $scope);
            if (null === $value) {
                continue;
            }
            $draft['attributes'][$mapping['commercetool']] = ['name' => $mapping['commercetool'], 'value' => $value];
        }

        if ([] === $draft['name']) {
            $draft['name'] = $this->fallbackLocalized($context, $fallback);
        }
        if ([] === $draft['slug']) {
            $draft['slug'] = $this->fallbackLocalized($context, $this->slug($fallback, $fallback));
        }
        $draft['attributes'] = array_values($draft['attributes']);

        return $draft;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $over
     *
     * @return array<string, mixed>
     */
    public function merge(array $base, array $over): array
    {
        $attributes = [];
        foreach (array_merge($base['attributes'], $over['attributes']) as $attribute) {
            $attributes[$attribute['name']] = $attribute;
        }
        $notices = $base['notices'] + $over['notices'];

        return [
            'name' => $this->overlay($base['name'], $over['name']),
            'slug' => $this->overlay($base['slug'], $over['slug']),
            'description' => $this->overlay($base['description'], $over['description']),
            'metaTitle' => $this->overlay($base['metaTitle'], $over['metaTitle']),
            'metaDescription' => $this->overlay($base['metaDescription'], $over['metaDescription']),
            'prices' => [] !== $over['prices'] ? $over['prices'] : $base['prices'],
            'attributes' => array_values($attributes),
            'categories' => array_values(array_unique(array_merge($base['categories'], $over['categories']))),
            'notices' => $notices,
        ];
    }

    /**
     * @param array<string, string> $base
     * @param array<string, string> $over
     *
     * @return array<string, string>
     */
    private function overlay(array $base, array $over): array
    {
        foreach ($over as $locale => $value) {
            if ('' !== $value) {
                $base[$locale] = $value;
            }
        }

        return $base;
    }

    /**
     * @return array<string, AttributeInterface>
     */
    private function attributeMap(): array
    {
        if (null !== $this->attributeCache) {
            return $this->attributeCache;
        }
        $attributes = [];
        foreach ($this->attributes->findAll() as $attribute) {
            if ($attribute instanceof AttributeInterface) {
                $attributes[$attribute->getCode()] = $attribute;
            }
        }

        return $this->attributeCache = $attributes;
    }

    /**
     * @return list<string>
     */
    private function categories(EntityWithValuesInterface $entity): array
    {
        if (!method_exists($entity, 'getCategories')) {
            return [];
        }
        $keys = [];
        foreach ($entity->getCategories() as $category) {
            if (null === $category->getParent()) {
                continue;
            }
            $key = ResourceKey::commerceTool($category->getCode());
            if (null !== $key) {
                $keys[$key] = $key;
            }
        }

        return array_values($keys);
    }

    /**
     * @return list<string|null>
     */
    private function locales(TransferContext $context, AttributeInterface $attribute, ?string $locale): array
    {
        if (!$attribute->isLocalizable()) {
            return [null];
        }
        if (null !== $locale) {
            return [$locale];
        }

        return array_values($context->localeMap);
    }

    /**
     * @param list<string|null> $locales
     *
     * @return array<string, string>
     */
    private function localizedText(
        TransferContext $context,
        EntityWithValuesInterface $entity,
        AttributeInterface $attribute,
        array $locales,
        ?string $scope,
        string $fallback,
        bool $asSlug,
    ): array {
        $localized = [];
        foreach ($context->locales as $ctLocale) {
            $akeneo = $attribute->isLocalizable() ? ($context->localeMap[$ctLocale] ?? null) : null;
            if ($attribute->isLocalizable() && (null === $akeneo || !\in_array($akeneo, $locales, true))) {
                continue;
            }
            $data = $this->scalar($this->read($entity, $attribute, $akeneo, $scope));
            if (!\is_string($data) || '' === trim($data)) {
                continue;
            }
            $localized[$ctLocale] = $asSlug ? $this->slug($data, $fallback) : trim($data);
        }

        return $localized;
    }

    /**
     * @return array<string, string>
     */
    private function fallbackLocalized(TransferContext $context, string $value): array
    {
        $locales = [] !== $context->locales ? $context->locales : ['en'];
        $localized = [];
        foreach ($locales as $locale) {
            $localized[$locale] = $value;
        }

        return $localized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function prices(TransferContext $context, EntityWithValuesInterface $entity, AttributeInterface $attribute, ?string $scope): array
    {
        $data = $this->read($entity, $attribute, $attribute->isLocalizable() ? (array_values($context->localeMap)[0] ?? null) : null, $scope);
        $prices = [];
        $rows = \is_array($data) ? $data : [];
        foreach ($rows as $row) {
            if (!isset($row['currency'], $row['amount'])) {
                continue;
            }
            $currency = $this->currency($context, (string) $row['currency']);
            if (null === $currency) {
                continue;
            }
            $prices[] = [
                'value' => [
                    'type' => 'centPrecision',
                    'currencyCode' => $currency,
                    'centAmount' => (int) round(((float) $row['amount']) * 100),
                    'fractionDigits' => 2,
                ],
            ];
        }

        return $prices;
    }

    /**
     * @param array<string, mixed> $definition
     * @param list<string|null>    $locales
     */
    private function attributeValue(
        TransferContext $context,
        EntityWithValuesInterface $entity,
        AttributeInterface $attribute,
        array $definition,
        array $locales,
        ?string $scope,
    ): mixed {
        $type = $definition['type']['name'] ?? 'text';
        if ('ltext' === $type || 'lenum' === $type) {
            $localized = [];
            foreach ($context->locales as $ctLocale) {
                $akeneo = $context->localeMap[$ctLocale] ?? null;
                if (null === $akeneo || !\in_array($akeneo, $locales, true)) {
                    continue;
                }
                $data = $this->scalar($this->read($entity, $attribute, $akeneo, $scope));
                if (\is_string($data) && '' !== $data) {
                    $localized[$ctLocale] = $data;
                }
            }

            return [] !== $localized ? $localized : null;
        }

        $locale = $attribute->isLocalizable() ? ($locales[0] ?? null) : null;
        $data = $this->read($entity, $attribute, $locale, $scope);
        if (null === $data || '' === $data || [] === $data) {
            return null;
        }

        return match ($type) {
            'number' => is_numeric($data) ? (float) $data : null,
            'boolean' => \is_bool($data) ? $data : null,
            'enum' => \is_string($data) ? $data : null,
            'set' => \is_array($data) ? array_values(array_filter($data, 'is_string')) : null,
            'date' => \is_string($data) ? $data : null,
            default => \is_scalar($data) ? (string) $data : null,
        };
    }

    private function read(EntityWithValuesInterface $entity, AttributeInterface $attribute, ?string $locale, ?string $scope): mixed
    {
        $value = $entity->getValue($attribute->getCode(), $attribute->isLocalizable() ? $locale : null, $attribute->isScopable() ? $scope : null);
        if (!$value instanceof ValueInterface) {
            return null;
        }

        return $this->normalize($value->getData());
    }

    private function normalize(mixed $data): mixed
    {
        if ($data instanceof \DateTimeInterface) {
            return $data->format('Y-m-d');
        }
        if (\is_object($data) && method_exists($data, 'getUnit') && method_exists($data, 'getData')) {
            return $data->getData();
        }
        if (\is_array($data) || $data instanceof \Traversable) {
            $prices = [];
            $codes = [];
            foreach ($data as $item) {
                if (\is_object($item) && method_exists($item, 'getCurrency') && method_exists($item, 'getData')) {
                    $prices[] = ['currency' => (string) $item->getCurrency(), 'amount' => (float) $item->getData()];
                } elseif (\is_string($item)) {
                    $codes[] = $item;
                } elseif (\is_object($item) && method_exists($item, 'getCode')) {
                    $codes[] = (string) $item->getCode();
                }
            }

            return [] !== $prices ? $prices : $codes;
        }
        if (\is_object($data) && method_exists($data, 'getCode')) {
            return (string) $data->getCode();
        }

        return \is_scalar($data) ? $data : null;
    }

    private function scalar(mixed $data): mixed
    {
        return \is_scalar($data) ? (string) $data : null;
    }

    private function currency(TransferContext $context, string $akeneo): ?string
    {
        foreach ($context->currencyMap as $ct => $mapped) {
            if ($mapped === $akeneo) {
                return $ct;
            }
        }

        return \in_array($akeneo, $context->currencies, true) ? $akeneo : null;
    }

    private function slug(string $value, string $fallback): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($value)));
        $slug = trim($slug, '-');

        return 1 === preg_match('/^[a-z0-9_-]{2,256}$/', $slug) ? $slug : $fallback;
    }
}
