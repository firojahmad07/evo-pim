<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Updater;

use Akeneo\Tool\Component\StorageUtils\Exception\InvalidObjectException;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Doctrine\Common\Util\ClassUtils;
use Spygar\CommerceTool\Domain\Model\Credential;

class CredentialUpdater implements ObjectUpdaterInterface
{
    public function update($credential, array $data, array $options = [])
    {
        if (!$credential instanceof Credential) {
            throw InvalidObjectException::objectExpected(ClassUtils::getClass($credential), Credential::class);
        }

        foreach ($data as $field => $value) {
            $this->setData($credential, (string) $field, $value);
        }

        $credential->touch();

        return $this;
    }

    private function setData(Credential $credential, string $field, mixed $value): void
    {
        switch ($field) {
            case 'code':
                $credential->setCode(trim((string) $value));
                break;
            case 'label':
                $credential->setLabel(trim((string) $value));
                break;
            case 'project_key':
                $credential->setProjectKey(trim((string) $value));
                break;
            case 'client_id':
                $credential->setClientId(trim((string) $value));
                break;
            case 'client_secret':
                $secret = trim((string) $value);
                if ('' !== $secret) {
                    $credential->setClientSecret($secret);
                }
                break;
            case 'region':
                $region = $this->nullableString($value);
                $credential->setRegion(null === $region ? null : strtolower($region));
                break;
            case 'auth_url':
                $credential->setAuthUrl($this->nullableString($value));
                break;
            case 'api_url':
                $credential->setApiUrl($this->nullableString($value));
                break;
            case 'scope':
                $credential->setScope($this->nullableString($value));
                break;
            case 'active':
                $credential->setActive((bool) $value);
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
