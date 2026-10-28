<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeRepository;
use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;

final class ImportedValues
{
    public function __construct(private AttributeRepository $attributes)
    {
    }

    /**
     * @param array<string, mixed>              $current
     * @param array<string, string>             $categoryIds
     * @param list<array<string, mixed>>        $ctAttributes
     *
     * @return array{categories: list<string>, values: array<string, list<array{locale: ?string, scope: ?string, data: mixed}>>}
     */
    public function extract(TransferContext $context, array $current, array $categoryIds, array $ctAttributes): array
    {
        $values = [];
        $this->addField($context, $values, 'name', \is_array($current['name'] ?? null) ? $current['name'] : []);
        $this->addField($context, $values, 'description', \is_array($current['description'] ?? null) ? $current['description'] : []);
        $this->addField($context, $values, 'metaTitle', \is_array($current['metaTitle'] ?? null) ? $current['metaTitle'] : []);
        $this->addField($context, $values, 'metaDescription', \is_array($current['metaDescription'] ?? null) ? $current['metaDescription'] : []);
        foreach ($ctAttributes as $attribute) {
            if (!\is_array($attribute) || !isset($attribute['name']) || !\is_string($attribute['name'])) {
                continue;
            }
            $this->addAttribute($context, $values, $attribute['name'], $attribute['value'] ?? null);
        }

        return [
            'categories' => $this->categories($current, $categoryIds),
            'values' => $values,
        ];
    }

    /**
     * @param array<string, list<array{locale: ?string, scope: ?string, data: mixed}>> $values
     * @param list<string>                                                              $allowed
     *
     * @return array<string, list<array{locale: ?string, scope: ?string, data: mixed}>>
     */
    public function only(array $values, array $allowed): array
    {
        $allowed = array_fill_keys($allowed, true);

        return array_filter($values, static fn (array $unused, string $code): bool => isset($allowed[$code]), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param array<string, mixed>  $current
     * @param array<string, string> $categoryIds
     *
     * @return list<string>
     */
    private function categories(array $current, array $categoryIds): array
    {
        $codes = [];
        foreach ($current['categories'] ?? [] as $category) {
            if (!\is_array($category)) {
                continue;
            }
            $id = isset($category['id']) && \is_string($category['id']) ? $category['id'] : '';
            $key = $category['obj']['key'] ?? $category['key'] ?? ($categoryIds[$id] ?? null);
            if (!\is_string($key)) {
                continue;
            }
            $code = ResourceKey::akeneo($key);
            if (null !== $code) {
                $codes[$code] = $code;
            }
        }

        return array_values($codes);
    }

    /**
     * @param array<string, list<array{locale: ?string, scope: ?string, data: mixed}>> $values
     * @param array<string, mixed>                                                      $localized
     */
    private function addField(TransferContext $context, array &$values, string $field, array $localized): void
    {
        $mapping = $this->mapping($context, $field);
        if (null === $mapping) {
            return;
        }
        $this->write($context, $values, $mapping, $localized);
    }

    /**
     * @param array<string, list<array{locale: ?string, scope: ?string, data: mixed}>> $values
     */
    private function addAttribute(TransferContext $context, array &$values, string $name, mixed $data): void
    {
        $mapping = $this->mapping($context, $name);
        $code = \is_array($mapping) ? $mapping['akeneo'] : ResourceKey::akeneo($name);
        if (!\is_string($code) || '' === $code) {
            return;
        }
        $attribute = $this->attributes->findOneByIdentifier($code);
        if (!$attribute instanceof AttributeInterface) {
            return;
        }
        $channel = \is_array($mapping) ? $mapping['channel'] : null;
        $mappedLocale = \is_array($mapping) ? $mapping['locale'] : null;
        $scope = $attribute->isScopable() ? ($channel ?? $context->scope()) : null;
        if ($attribute->isScopable() && null === $scope) {
            return;
        }
        if (\is_array($data) && $this->isLocalized($data)) {
            foreach ($context->localeMap as $ctLocale => $akeneoLocale) {
                if (!isset($data[$ctLocale])) {
                    continue;
                }
                $normalized = $this->normalize($attribute, $data[$ctLocale]);
                if (null === $normalized) {
                    continue;
                }
                $values[$code][] = [
                    'locale' => $attribute->isLocalizable() ? $akeneoLocale : null,
                    'scope' => $scope,
                    'data' => $normalized,
                ];
                if (!$attribute->isLocalizable()) {
                    break;
                }
            }

            return;
        }
        $normalized = $this->normalize($attribute, $data);
        if (null === $normalized) {
            return;
        }
        $values[$code][] = [
            'locale' => $attribute->isLocalizable() ? ($mappedLocale ?? (array_values($context->localeMap)[0] ?? null)) : null,
            'scope' => $scope,
            'data' => $normalized,
        ];
    }

    /**
     * @param array<string, list<array{locale: ?string, scope: ?string, data: mixed}>> $values
     * @param array{akeneo: string, commercetool: string, variant: ?string, type: string, locale: ?string, channel: ?string} $mapping
     * @param array<string, mixed> $localized
     */
    private function write(TransferContext $context, array &$values, array $mapping, array $localized): void
    {
        $attribute = $this->attributes->findOneByIdentifier($mapping['akeneo']);
        if (!$attribute instanceof AttributeInterface) {
            return;
        }
        $this->addAttribute($context, $values, $mapping['commercetool'], $localized);
    }

    /**
     * @return array{akeneo: string, commercetool: string, variant: ?string, type: string, locale: ?string, channel: ?string}|null
     */
    private function mapping(TransferContext $context, string $name): ?array
    {
        foreach ($context->mappings as $mapping) {
            if (0 === strcasecmp($mapping['commercetool'], $name)) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isLocalized(array $data): bool
    {
        if ([] === $data) {
            return false;
        }
        foreach ($data as $key => $value) {
            if (!\is_string($key) || !\is_scalar($value)) {
                return false;
            }
        }

        return true;
    }

    private function normalize(AttributeInterface $attribute, mixed $data): mixed
    {
        return match ($attribute->getType()) {
            AttributeTypes::BOOLEAN => \is_bool($data) ? $data : null,
            AttributeTypes::NUMBER, AttributeTypes::METRIC => is_numeric($data) ? (float) $data : null,
            AttributeTypes::DATE => \is_string($data) ? $data : null,
            AttributeTypes::OPTION_SIMPLE_SELECT => \is_string($data) ? (ResourceKey::akeneo($data) ?? $data) : null,
            AttributeTypes::OPTION_MULTI_SELECT => \is_array($data)
                ? array_values(array_filter(array_map(
                    static fn (mixed $item): ?string => \is_string($item) ? (ResourceKey::akeneo($item) ?? $item) : null,
                    $data
                )))
                : null,
            AttributeTypes::TEXT, AttributeTypes::TEXTAREA, AttributeTypes::IDENTIFIER => \is_scalar($data) ? (string) $data : null,
            default => \is_scalar($data) ? (string) $data : null,
        };
    }
}
