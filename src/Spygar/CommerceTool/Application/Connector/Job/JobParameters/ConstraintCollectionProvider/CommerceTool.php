<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Job\JobParameters\ConstraintCollectionProvider;

use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\ConstraintCollectionProviderInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CommerceTool implements ConstraintCollectionProviderInterface
{
    /**
     * @param list<string> $jobNames
     */
    public function __construct(private array $jobNames, private Connection $connection)
    {
    }

    public function supports(JobInterface $job): bool
    {
        return \in_array($job->getName(), $this->jobNames, true);
    }

    public function getConstraintCollection(): Collection
    {
        return new Collection([
            'fields' => [
                'credential' => [
                    new NotBlank(['message' => 'Select a credential.']),
                    new Callback(['callback' => function (mixed $value, ExecutionContextInterface $context): void {
                        if (!\is_string($value) || '' === trim($value)) {
                            return;
                        }
                        if (1 !== preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
                            $context->buildViolation('Select a credential.')->addViolation();

                            return;
                        }

                        try {
                            $row = $this->connection->fetchAssociative(
                                'SELECT active FROM spygar_commercetool_credential WHERE code = ?',
                                [$value]
                            );
                        } catch (\Throwable) {
                            $context->buildViolation('The credential could not be loaded.')->addViolation();

                            return;
                        }

                        if (!\is_array($row)) {
                            $context->buildViolation('Select a credential that exists.')->addViolation();

                            return;
                        }
                        if (1 !== (int) $row['active']) {
                            $context->buildViolation('The selected credential is disabled.')->addViolation();
                        }
                    }]),
                ],
                'users_to_notify' => new Optional(new Type('array')),
                'send_email' => new Optional(new Type('bool')),
            ],
            'allowExtraFields' => true,
            'allowMissingFields' => true,
        ]);
    }
}
