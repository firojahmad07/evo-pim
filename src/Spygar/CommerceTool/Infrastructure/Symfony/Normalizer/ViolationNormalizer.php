<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Infrastructure\Symfony\Normalizer;

use Symfony\Component\Validator\ConstraintViolation;

final class ViolationNormalizer
{
    private const PATHS = [
        'code' => 'code',
        'label' => 'label',
        'projectKey' => 'project_key',
        'clientId' => 'client_id',
        'clientSecret' => 'client_secret',
        'region' => 'region',
        'authUrl' => 'auth_url',
        'apiUrl' => 'api_url',
        'scope' => 'scope',
        'active' => 'active',
        'akeneoAttribute' => 'akeneo_attribute',
        'commercetoolsAttribute' => 'commercetools_attribute',
        'locale' => 'locale',
        'channel' => 'channel',
        'enabled' => 'enabled',
        'exportProducts' => 'export_products',
        'exportCategories' => 'export_categories',
        'importProducts' => 'import_products',
        'debug' => 'debug',
    ];

    /**
     * @return array{path: string, attribute: string, message: string, global: bool}
     */
    public function normalize(ConstraintViolation $violation): array
    {
        $property = $violation->getPropertyPath();
        $path = self::PATHS[$property] ?? $property;

        if ('' === $path) {
            return [
                'path' => '',
                'attribute' => '',
                'message' => (string) $violation->getMessage(),
                'global' => true,
            ];
        }

        return [
            'path' => $path,
            'attribute' => $path,
            'message' => (string) $violation->getMessage(),
            'global' => false,
        ];
    }
}
