<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Job\JobParameters\DefaultValuesProvider;

use Akeneo\Tool\Component\Batch\Job\JobInterface;
use Akeneo\Tool\Component\Batch\Job\JobParameters\DefaultValuesProviderInterface;

final class CommerceTool implements DefaultValuesProviderInterface
{
    /**
     * @param list<string> $jobNames
     */
    public function __construct(private array $jobNames)
    {
    }

    public function supports(JobInterface $job): bool
    {
        return \in_array($job->getName(), $this->jobNames, true);
    }

    public function getDefaultValues(): array
    {
        return [
            'credential' => null,
            'users_to_notify' => [],
        ];
    }
}
