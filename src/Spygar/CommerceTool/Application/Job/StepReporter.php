<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Akeneo\Tool\Component\Batch\Item\DataInvalidItem;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;

final class StepReporter
{
    private int $reads = 0;

    private int $warnings = 0;

    public function __construct(private StepExecution $step, private JobRepositoryInterface $jobRepository)
    {
    }

    public function note(string $key, string $value): void
    {
        $this->step->addSummaryInfo($key, $value);
    }

    public function read(): void
    {
        $this->step->incrementSummaryInfo('read');
        ++$this->reads;
        if (0 === $this->reads % 25) {
            $this->jobRepository->updateStepExecution($this->step);
        }
    }

    public function created(): void
    {
        $this->step->incrementSummaryInfo('created');
    }

    public function updated(): void
    {
        $this->step->incrementSummaryInfo('updated');
    }

    /**
     * @param array<string, scalar|null> $item
     */
    public function warn(string $message, array $item): void
    {
        $this->record($message, $item);
    }

    /**
     * @param array<string, scalar|null> $item
     */
    public function skip(string $message, array $item): void
    {
        $this->step->incrementSummaryInfo('skipped');
        $this->record($message, $item);
    }

    /**
     * @param array<string, scalar|null> $item
     */
    private function record(string $message, array $item): void
    {
        if ($this->warnings >= 100) {
            return;
        }
        ++$this->warnings;
        $this->step->addWarning(mb_substr(trim($message), 0, 400), [], new DataInvalidItem($item));
        if (100 === $this->warnings) {
            $this->step->addWarning('Further warnings were omitted.', [], new DataInvalidItem(['code' => '']));
        }
    }
}
