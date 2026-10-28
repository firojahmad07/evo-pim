<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Writer\CommerceTool;

use Spygar\CommerceTool\Application\Connector\Writer\AbstractExchangeWriter;
use Spygar\CommerceTool\Application\Job\Transfer\ProductModelTransfer;

final class ProductModelWriter extends AbstractExchangeWriter
{
    public function __construct(ProductModelTransfer $transfer)
    {
        parent::__construct($transfer, 'export');
    }
}
