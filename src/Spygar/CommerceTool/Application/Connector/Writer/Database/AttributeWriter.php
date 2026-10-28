<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Writer\Database;

use Spygar\CommerceTool\Application\Connector\Writer\AbstractExchangeWriter;
use Spygar\CommerceTool\Application\Job\Transfer\AttributeTransfer;

final class AttributeWriter extends AbstractExchangeWriter
{
    public function __construct(AttributeTransfer $transfer)
    {
        parent::__construct($transfer, 'import');
    }
}
