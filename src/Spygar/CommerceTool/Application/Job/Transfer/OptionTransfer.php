<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job\Transfer;

use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeOptionRepository;
use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeRepository;
use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Structure\Component\Model\AttributeOptionInterface;
use Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Spygar\CommerceTool\Application\Job\AkeneoPersister;
use Spygar\CommerceTool\Application\Job\AttributeDefinitionBuilder;
use Spygar\CommerceTool\Application\Job\ProductTypeRegistry;
use Spygar\CommerceTool\Application\Job\ResourceKey;
use Spygar\CommerceTool\Application\Job\StepReporter;
use Spygar\CommerceTool\Application\Job\TransferContext;

final class OptionTransfer
{
    public function __construct(
        private AttributeRepository $attributes,
        private AttributeOptionRepository $options,
        private SimpleFactoryInterface $factory,
        private ObjectUpdaterInterface $updater,
        private SaverInterface $saver,
        private ProductTypeRegistry $productTypes,
        private AttributeDefinitionBuilder $definitions,
        private AkeneoPersister $persister,
    ) {
    }

    public function export(TransferContext $context, StepReporter $reporter): void
    {
        $type = $this->productTypes->load($context->credential, $context->token);
        $existing = $this->productTypes->definitions($type);
        $actions = [];
        foreach ($this->attributes->findBy([], ['sortOrder' => 'ASC']) as $attribute) {
            if (!$attribute instanceof AttributeInterface || !\in_array($attribute->getType(), [AttributeTypes::OPTION_SIMPLE_SELECT, AttributeTypes::OPTION_MULTI_SELECT], true)) {
                continue;
            }
            $name = ResourceKey::commerceTool($attribute->getCode());
            if (null === $name) {
                $reporter->read();
                $reporter->skip('The attribute code cannot be used as a CommerceTool attribute name.', ['code' => $attribute->getCode()]);
                continue;
            }
            $values = $this->definitions->values($this->options->findBy(['attribute' => $attribute], ['sortOrder' => 'ASC']));
            if ([] === $values) {
                continue;
            }
            if (!isset($existing[$name])) {
                $draft = $this->definitions->draft($attribute, [], $values, $context->localeMap, $context->locales);
                if (null === $draft) {
                    continue;
                }
                $actions[] = ['action' => 'addAttributeDefinition', 'attribute' => $draft];
                $existing[$name] = $draft;
                foreach ($values as $value) {
                    $reporter->read();
                    $reporter->created();
                }
                continue;
            }

            $present = [];
            foreach ($this->definitions->enumValues($existing[$name]) as $value) {
                $present[$value['key']] = $value['label'];
            }
            foreach ($values as $value) {
                $reporter->read();
                if (!isset($present[$value['key']])) {
                    $actions[] = ['action' => 'addPlainEnumValue', 'attributeName' => $name, 'value' => $value];
                    $reporter->created();
                    continue;
                }
                if ($present[$value['key']] !== $value['label']) {
                    $actions[] = [
                        'action' => 'changePlainEnumValueLabel',
                        'attributeName' => $name,
                        'newValue' => $value,
                    ];
                    $reporter->updated();
                    continue;
                }
                $reporter->updated();
            }
        }

        if ([] !== $actions) {
            $this->productTypes->apply($context->credential, $context->token, $type, $actions);
        }
    }

    public function import(TransferContext $context, StepReporter $reporter): void
    {
        $definitions = $this->productTypes->definitions($this->productTypes->load($context->credential, $context->token));
        foreach ($definitions as $name => $definition) {
            $typeName = $definition['type']['name'] ?? '';
            $element = $definition['type']['elementType']['name'] ?? '';
            if (!\in_array($typeName, ['enum', 'lenum'], true) && !('set' === $typeName && \in_array($element, ['enum', 'lenum'], true))) {
                continue;
            }
            $attributeCode = ResourceKey::akeneo($name);
            if (null === $attributeCode) {
                continue;
            }
            $attribute = $this->attributes->findOneByIdentifier($attributeCode);
            if (!$attribute instanceof AttributeInterface) {
                $reporter->read();
                $reporter->skip('Import the attribute before its options.', ['code' => $name]);
                continue;
            }
            $sort = 0;
            foreach ($this->definitions->enumValues($definition) as $value) {
                ++$sort;
                $reporter->read();
                $optionCode = ResourceKey::akeneo($value['key']);
                if (null === $optionCode) {
                    $reporter->skip('The option key cannot be stored in Akeneo.', ['code' => $name.'.'.$value['key']]);
                    continue;
                }
                $this->store($context, $reporter, $attribute, $optionCode, $value['label'], $sort);
            }
        }
    }

    private function store(TransferContext $context, StepReporter $reporter, AttributeInterface $attribute, string $optionCode, string $label, int $sort): void
    {
        $item = ['code' => $attribute->getCode().'.'.$optionCode];
        $existing = $this->options->findOneBy(['code' => $optionCode, 'attribute' => $attribute]);
        $isNew = !$existing instanceof AttributeOptionInterface;
        $option = $isNew ? $this->factory->create() : $existing;
        $labels = [];
        foreach ($context->localeMap as $akeneoLocale) {
            $labels[$akeneoLocale] = $label;
        }
        try {
            $this->updater->update($option, [
                'code' => $optionCode,
                'attribute' => $attribute->getCode(),
                'sort_order' => $sort,
                'labels' => $labels,
            ]);
        } catch (\Throwable $exception) {
            $reporter->skip($exception->getMessage(), $item);

            return;
        }
        $this->persister->save($option, $this->saver, $reporter, $item, $isNew);
    }
}
