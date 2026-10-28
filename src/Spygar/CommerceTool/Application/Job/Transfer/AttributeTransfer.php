<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job\Transfer;

use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeGroupRepository;
use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeOptionRepository;
use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\AttributeRepository;
use Akeneo\Pim\Structure\Component\AttributeTypes;
use Akeneo\Pim\Structure\Component\Factory\AttributeFactory;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Spygar\CommerceTool\Application\Job\AkeneoPersister;
use Spygar\CommerceTool\Application\Job\AttributeDefinitionBuilder;
use Spygar\CommerceTool\Application\Job\ProductTypeRegistry;
use Spygar\CommerceTool\Application\Job\ResourceKey;
use Spygar\CommerceTool\Application\Job\StepReporter;
use Spygar\CommerceTool\Application\Job\TransferContext;

final class AttributeTransfer
{
    public function __construct(
        private AttributeRepository $attributes,
        private AttributeOptionRepository $options,
        private AttributeGroupRepository $groups,
        private AttributeFactory $factory,
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
            if (!$attribute instanceof AttributeInterface || AttributeTypes::IDENTIFIER === $attribute->getType()) {
                continue;
            }
            $reporter->read();
            $draft = $this->definitions->draft(
                $attribute,
                $this->labels($attribute),
                $this->definitions->values($this->options->findBy(['attribute' => $attribute], ['sortOrder' => 'ASC'])),
                $context->localeMap,
                $context->locales
            );
            if (null === $draft) {
                $reporter->skip('This attribute type cannot be sent to CommerceTool.', ['code' => $attribute->getCode()]);
                continue;
            }
            $name = (string) $draft['name'];
            if (isset($existing[$name])) {
                $actions[] = ['action' => 'changeLabel', 'attributeName' => $name, 'label' => $draft['label']];
                $reporter->updated();
                continue;
            }
            $actions[] = ['action' => 'addAttributeDefinition', 'attribute' => $draft];
            $existing[$name] = $draft;
            $reporter->created();
        }

        if ([] !== $actions) {
            $this->productTypes->apply($context->credential, $context->token, $type, $actions);
        }
    }

    public function import(TransferContext $context, StepReporter $reporter): void
    {
        $group = $this->groupCode();
        if (null === $group) {
            throw new \RuntimeException('Akeneo has no attribute group to receive CommerceTool attributes.');
        }

        foreach ($this->productTypes->load($context->credential, $context->token)['attributes'] ?? [] as $definition) {
            if (!\is_array($definition)) {
                continue;
            }
            $reporter->read();
            $this->store($context, $reporter, $definition, $group);
        }
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function store(TransferContext $context, StepReporter $reporter, array $definition, string $group): void
    {
        $name = isset($definition['name']) && \is_string($definition['name']) ? $definition['name'] : '';
        $code = ResourceKey::akeneo($name);
        $item = ['code' => $name];
        if (null === $code) {
            $reporter->skip('The CommerceTool attribute name cannot be stored in Akeneo.', $item);

            return;
        }
        $akeneoType = $this->akeneoType(\is_array($definition['type'] ?? null) ? $definition['type'] : []);
        if (null === $akeneoType) {
            $reporter->skip('This CommerceTool attribute type is not imported.', $item);

            return;
        }

        $labels = $this->akeneoLabels($context, \is_array($definition['label'] ?? null) ? $definition['label'] : []);
        $existing = $this->attributes->findOneByIdentifier($code);
        if ($existing instanceof AttributeInterface) {
            try {
                $this->updater->update($existing, ['labels' => $labels]);
            } catch (\Throwable $exception) {
                $reporter->skip($exception->getMessage(), $item);

                return;
            }
            $this->persister->save($existing, $this->saver, $reporter, $item, false);

            return;
        }

        $attribute = $this->factory->createAttribute($akeneoType);
        $data = [
            'code' => $code,
            'type' => $akeneoType,
            'group' => $group,
            'localizable' => 'ltext' === ($definition['type']['name'] ?? null),
            'scopable' => false,
            'unique' => false,
            'useable_as_grid_filter' => false,
            'labels' => $labels,
        ];
        if (\in_array($akeneoType, [AttributeTypes::NUMBER, AttributeTypes::METRIC], true)) {
            $data['decimals_allowed'] = true;
            $data['negative_allowed'] = true;
        }
        try {
            $this->updater->update($attribute, $data);
        } catch (\Throwable $exception) {
            $reporter->skip($exception->getMessage(), $item);

            return;
        }
        $this->persister->save($attribute, $this->saver, $reporter, $item, true);
    }

    /**
     * @param array<string, mixed> $type
     */
    private function akeneoType(array $type): ?string
    {
        $name = $type['name'] ?? '';
        $element = \is_array($type['elementType'] ?? null) ? ($type['elementType']['name'] ?? '') : '';

        return match ($name) {
            'text', 'ltext' => AttributeTypes::TEXT,
            'number' => AttributeTypes::NUMBER,
            'boolean' => AttributeTypes::BOOLEAN,
            'date' => AttributeTypes::DATE,
            'enum', 'lenum' => AttributeTypes::OPTION_SIMPLE_SELECT,
            'set' => 'enum' === $element || 'lenum' === $element ? AttributeTypes::OPTION_MULTI_SELECT : null,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $labels
     *
     * @return array<string, string>
     */
    private function akeneoLabels(TransferContext $context, array $labels): array
    {
        $mapped = [];
        foreach ($context->localeMap as $ctLocale => $akeneoLocale) {
            $label = $labels[$ctLocale] ?? null;
            if (\is_string($label) && '' !== trim($label)) {
                $mapped[$akeneoLocale] = trim($label);
            }
        }

        return $mapped;
    }

    /**
     * @return array<string, string>
     */
    private function labels(AttributeInterface $attribute): array
    {
        $labels = [];
        foreach ($attribute->getTranslations() as $translation) {
            $locale = method_exists($translation, 'getLocale') ? $translation->getLocale() : null;
            $label = method_exists($translation, 'getLabel') ? $translation->getLabel() : null;
            if (\is_string($locale) && \is_string($label) && '' !== trim($label)) {
                $labels[$locale] = trim($label);
            }
        }

        return $labels;
    }

    private function groupCode(): ?string
    {
        $group = $this->groups->findOneByIdentifier('other');
        if (null !== $group) {
            return $group->getCode();
        }
        $groups = $this->groups->findBy([], ['sortOrder' => 'ASC'], 1);

        return isset($groups[0]) ? $groups[0]->getCode() : null;
    }
}
