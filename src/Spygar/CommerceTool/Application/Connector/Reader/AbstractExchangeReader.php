<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Reader;

use Akeneo\Tool\Component\Batch\Item\InitializableInterface;
use Akeneo\Tool\Component\Batch\Item\ItemReaderInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface;
use Spygar\CommerceTool\Application\Connector\Exchange\ExchangeContextFactory;

abstract class AbstractExchangeReader implements ItemReaderInterface, InitializableInterface, StepExecutionAwareInterface
{
    private ?StepExecution $stepExecution = null;

    private bool $consumed = false;

    public function __construct(
        private ExchangeContextFactory $factory,
        private string $entity,
        private string $direction,
    ) {
    }

    public function setStepExecution(StepExecution $stepExecution): void
    {
        $this->stepExecution = $stepExecution;
    }

    public function initialize(): void
    {
        $this->consumed = false;
    }

    public function read(): mixed
    {
        if ($this->consumed) {
            return null;
        }
        $this->consumed = true;
        if (null === $this->stepExecution) {
            throw new \RuntimeException('The CommerceTool job has no step execution.');
        }

        return $this->factory->open($this->stepExecution, $this->entity, $this->direction);
    }
}
