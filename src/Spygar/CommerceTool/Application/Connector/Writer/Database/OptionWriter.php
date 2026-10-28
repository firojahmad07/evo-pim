<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Writer\Database;

use Spygar\CommerceTool\Application\Connector\Writer\AbstractExchangeWriter;
use Spygar\CommerceTool\Application\Job\Transfer\OptionTransfer;

final class OptionWriter extends AbstractExchangeWriter
{
    public function __construct(OptionTransfer $transfer)
    {
        parent::__construct($transfer, 'import');
    }
}
