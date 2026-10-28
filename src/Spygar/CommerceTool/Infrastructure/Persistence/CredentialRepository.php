<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Infrastructure\Persistence;

use Akeneo\Tool\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Doctrine\ORM\EntityRepository;
use Spygar\CommerceTool\Domain\Model\Credential;

class CredentialRepository extends EntityRepository implements IdentifiableObjectRepositoryInterface
{
    public function findOneByIdentifier($identifier): ?Credential
    {
        return $this->findOneBy(['code' => $identifier]);
    }

    public function getIdentifierProperties(): array
    {
        return ['code'];
    }
}
