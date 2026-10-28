<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Exchange;

use Spygar\CommerceTool\Application\Job\StepReporter;
use Spygar\CommerceTool\Application\Job\TransferContext;

final class ExchangeEnvelope
{
    public function __construct(
        public readonly TransferContext $context,
        public readonly StepReporter $reporter,
    ) {
    }
}
