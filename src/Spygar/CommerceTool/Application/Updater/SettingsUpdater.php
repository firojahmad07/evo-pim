<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Updater;

use Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Doctrine\Common\Util\ClassUtils;
use Spygar\CommerceTool\Domain\Model\Settings;

class SettingsUpdater implements ObjectUpdaterInterface
{
    public function update($settings, array $data, array $options = [])
    {
        if (!$settings instanceof Settings) {
            throw InvalidObjectException::objectExpected(ClassUtils::getClass($settings), Settings::class);
        }

        foreach ($data as $field => $value) {
            $this->setData($settings, (string) $field, $value);
        }

        $settings->touch();

        return $this;
    }

    private function setData(Settings $settings, string $field, mixed $value): void
    {
        switch ($field) {
            case 'enabled':
                $settings->setEnabled((bool) $value);
                break;
            case 'export_products':
                $settings->setExportProducts((bool) $value);
                break;
            case 'export_categories':
                $settings->setExportCategories((bool) $value);
                break;
            case 'import_products':
                $settings->setImportProducts((bool) $value);
                break;
            case 'debug':
                $settings->setDebug((bool) $value);
                break;
        }
    }
}
