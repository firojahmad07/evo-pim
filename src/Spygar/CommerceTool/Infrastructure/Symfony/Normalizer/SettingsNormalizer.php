<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Infrastructure\Symfony\Normalizer;

use Spygar\CommerceTool\Domain\Model\Settings;

final class SettingsNormalizer
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(Settings $settings): array
    {
        return [
            'code' => $settings->getCode(),
            'enabled' => $settings->isEnabled(),
            'export_products' => $settings->isExportProducts(),
            'export_categories' => $settings->isExportCategories(),
            'import_products' => $settings->isImportProducts(),
            'debug' => $settings->isDebug(),
            'meta' => [
                'id' => $settings->getId(),
                'model_type' => 'commercetool_settings',
            ],
        ];
    }
}
