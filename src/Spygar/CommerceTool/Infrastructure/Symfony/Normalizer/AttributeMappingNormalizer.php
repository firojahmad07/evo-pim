<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Infrastructure\Symfony\Normalizer;

use Spygar\CommerceTool\Domain\Model\AttributeMapping;

final class AttributeMappingNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(AttributeMapping $mapping): array
    {
        return [
            'code' => $mapping->getCode(),
            'akeneo_attribute' => $mapping->getAkeneoAttribute(),
            'commercetools_attribute' => $mapping->getCommercetoolsAttribute(),
            'variant_akeneo_attribute' => $mapping->getVariantAkeneoAttribute(),
            'mapping_type' => $mapping->getMappingType(),
            'position' => $mapping->getPosition(),
            'locale' => $mapping->getLocale(),
            'channel' => $mapping->getChannel(),
            'active' => $mapping->isActive(),
            'meta' => [
                'id' => $mapping->getId(),
                'model_type' => 'commercetool_attribute_mapping',
            ],
        ];
    }
}
