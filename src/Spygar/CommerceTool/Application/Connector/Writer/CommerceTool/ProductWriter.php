<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Writer\CommerceTool;

use Spygar\CommerceTool\Application\Connector\Writer\AbstractExchangeWriter;
use Spygar\CommerceTool\Application\Job\Transfer\ProductTransfer;

final class ProductWriter extends AbstractExchangeWriter
{
    public function __construct(ProductTransfer $transfer)
    {
        parent::__construct($transfer, 'export');
    }
}
