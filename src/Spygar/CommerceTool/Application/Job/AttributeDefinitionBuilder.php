<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Structure\Component\Model\AttributeOptionInterface;

final class AttributeDefinitionBuilder
{
    /**
     * @param list<array{key: string, label: string}> $values
     * @param array<string, string>                   $labels
     * @param array<string, string>                   $localeMap
     * @param list<string>                            $ctLocales
     *
     * @return array<string, mixed>|null
     */
    public function draft(AttributeInterface $attribute, array $labels, array $values, array $localeMap, array $ctLocales): ?array
    {
        $name = ResourceKey::commerceTool($attribute->getCode());
        if (null === $name) {
            return null;
        }
        $type = $this->type($attribute, $values);
        if (null === $type) {
            return null;
        }

        return [
            'type' => $type,
            'name' => $name,
            'label' => $this->label($labels, $localeMap, $ctLocales, $attribute->getCode()),
            'isRequired' => false,
            'attributeConstraint' => 'None',
            'inputHint' => AttributeTypes::TEXTAREA === $attribute->getType() ? 'MultiLine' : 'SingleLine',
            'isSearchable' => false,
        ];
    }

    /**
     * @param list<array{key: string, label: string}> $values
     *
     * @return array<string, mixed>|null
     */
    public function type(AttributeInterface $attribute, array $values): ?array
    {
        $enum = static function (array $values): array {
            return [] !== $values ? $values : [['key' => 'akeneo', 'label' => 'Akeneo']];
        };

        return match ($attribute->getType()) {
            AttributeTypes::TEXT, AttributeTypes::IDENTIFIER => ['name' => $attribute->isLocalizable() ? 'ltext' : 'text'],
            AttributeTypes::TEXTAREA => ['name' => $attribute->isLocalizable() ? 'ltext' : 'text'],
            AttributeTypes::NUMBER, AttributeTypes::METRIC => ['name' => 'number'],
            AttributeTypes::BOOLEAN => ['name' => 'boolean'],
            AttributeTypes::DATE => ['name' => 'date'],
            AttributeTypes::OPTION_SIMPLE_SELECT => ['name' => 'enum', 'values' => $enum($values)],
            AttributeTypes::OPTION_MULTI_SELECT => [
                'name' => 'set',
                'elementType' => ['name' => 'enum', 'values' => $enum($values)],
            ],
            default => null,
        };
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function values(iterable $options): array
    {
        $values = [];
        foreach ($options as $option) {
            if (!$option instanceof AttributeOptionInterface) {
                continue;
            }
            $key = ResourceKey::commerceTool($option->getCode()) ?? (1 === preg_match('/^[A-Za-z0-9_-]{1,256}$/', $option->getCode()) ? $option->getCode() : null);
            if (null === $key || isset($values[$key])) {
                continue;
            }
            $label = $option->getCode();
            foreach ($option->getOptionValues() as $optionValue) {
                $text = method_exists($optionValue, 'getValue') ? $optionValue->getValue() : null;
                if (\is_string($text) && '' !== trim($text)) {
                    $label = trim($text);
                    break;
                }
            }
            $values[$key] = ['key' => $key, 'label' => $label];
        }

        return array_values($values);
    }

    /**
     * @param array<string, string> $labels
     * @param array<string, string> $localeMap
     * @param list<string>          $ctLocales
     *
     * @return array<string, string>
     */
    public function label(array $labels, array $localeMap, array $ctLocales, string $fallback): array
    {
        $localized = [];
        $locales = [] !== $ctLocales ? $ctLocales : array_keys($localeMap);
        foreach ($locales as $ct) {
            $akeneo = $localeMap[$ct] ?? null;
            $label = \is_string($akeneo) ? trim((string) ($labels[$akeneo] ?? '')) : '';
            $localized[$ct] = '' !== $label ? $label : $fallback;
        }
        if ([] === $localized) {
            $localized['en'] = $fallback;
        }

        return $localized;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return list<array{key: string, label: string}>
     */
    public function enumValues(array $definition): array
    {
        $type = \is_array($definition['type'] ?? null) ? $definition['type'] : [];
        $values = $type['values'] ?? null;
        if (!\is_array($values) && isset($type['elementType']) && \is_array($type['elementType'])) {
            $values = $type['elementType']['values'] ?? [];
        }
        if (!\is_array($values)) {
            return [];
        }

        $normalized = [];
        foreach ($values as $value) {
            if (!\is_array($value) || !isset($value['key']) || !\is_string($value['key'])) {
                continue;
            }
            $label = $value['label'] ?? $value['key'];
            if (\is_array($label)) {
                $label = reset($label);
            }
            $normalized[] = [
                'key' => $value['key'],
                'label' => \is_string($label) && '' !== $label ? $label : $value['key'],
            ];
        }

        return $normalized;
    }
}
