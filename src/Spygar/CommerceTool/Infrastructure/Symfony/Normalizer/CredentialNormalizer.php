<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Infrastructure\Symfony\Normalizer;

use Spygar\CommerceTool\Domain\Model\Credential;

final class CredentialNormalizer
{
    /**
     * The client secret is never returned. An empty value on save keeps the stored secret.
     *
     * @return array<string, mixed>
     */
    public function normalize(Credential $credential): array
    {
        return [
            'code' => $credential->getCode(),
            'label' => $credential->getLabel(),
            'project_key' => $credential->getProjectKey(),
            'client_id' => $credential->getClientId(),
            'client_secret' => '',
            'region' => $credential->getRegion(),
            'auth_url' => $credential->getAuthUrl(),
            'api_url' => $credential->getApiUrl(),
            'scope' => $credential->getScope(),
            'active' => $credential->isActive(),
            'meta' => [
                'id' => $credential->getId(),
                'model_type' => 'commercetool_credential',
            ],
        ];
    }
}
