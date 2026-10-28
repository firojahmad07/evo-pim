<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Writer\CommerceTool;

use Spygar\CommerceTool\Application\Connector\Writer\AbstractExchangeWriter;
use Spygar\CommerceTool\Application\Job\Transfer\CategoryTransfer;

final class CategoryWriter extends AbstractExchangeWriter
{
    public function __construct(CategoryTransfer $transfer)
    {
        parent::__construct($transfer, 'export');
    }
}
