<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Updater;

use Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Doctrine\Common\Util\ClassUtils;
use Spygar\CommerceTool\Domain\Model\AttributeMapping;

class AttributeMappingUpdater implements ObjectUpdaterInterface
{
    public function update($mapping, array $data, array $options = [])
    {
        if (!$mapping instanceof AttributeMapping) {
            throw InvalidObjectException::objectExpected(ClassUtils::getClass($mapping), AttributeMapping::class);
        }

        foreach ($data as $field => $value) {
            $this->setData($mapping, (string) $field, $value);
        }

        $mapping->touch();

        return $this;
    }

    private function setData(AttributeMapping $mapping, string $field, mixed $value): void
    {
        switch ($field) {
            case 'code':
                $mapping->setCode(trim((string) $value));
                break;
            case 'akeneo_attribute':
                $mapping->setAkeneoAttribute(trim((string) $value));
                break;
            case 'commercetools_attribute':
                $mapping->setCommercetoolsAttribute(trim((string) $value));
                break;
            case 'variant_akeneo_attribute':
                $mapping->setVariantAkeneoAttribute($this->nullableString($value));
                break;
            case 'mapping_type':
                $mapping->setMappingType(trim((string) $value));
                break;
            case 'position':
                $mapping->setPosition((int) $value);
                break;
            case 'locale':
                $mapping->setLocale($this->nullableString($value));
                break;
            case 'channel':
                $mapping->setChannel($this->nullableString($value));
                break;
            case 'active':
                $mapping->setActive((bool) $value);
                break;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
