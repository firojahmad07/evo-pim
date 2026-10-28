<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AkeneoPersister
{
    public function __construct(private ValidatorInterface $validator, private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, scalar|null> $item
     */
    public function save(object $entity, SaverInterface $saver, StepReporter $reporter, array $item, bool $isNew): bool
    {
        $violations = $this->validator->validate($entity);
        if (0 < $violations->count()) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = trim($violation->getPropertyPath().' '.$violation->getMessage());
            }
            $this->discard($entity);
            $reporter->skip(implode(' ', $messages), $item);

            return false;
        }

        try {
            $saver->save($entity);
        } catch (\Throwable $exception) {
            $this->discard($entity);
            $reporter->skip($exception->getMessage(), $item);

            return false;
        }

        if ($isNew) {
            $reporter->created();
        } else {
            $reporter->updated();
        }
        if ($this->entityManager->contains($entity)) {
            $this->entityManager->detach($entity);
        }

        return true;
    }

    private function discard(object $entity): void
    {
        if ($this->entityManager->contains($entity)) {
            $this->entityManager->refresh($entity);
            $this->entityManager->detach($entity);
        }
    }
}
