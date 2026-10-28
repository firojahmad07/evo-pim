<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Reader\Database;

use Spygar\CommerceTool\Application\Connector\Exchange\ExchangeContextFactory;
use Spygar\CommerceTool\Application\Connector\Reader\AbstractExchangeReader;

final class AttributeReader extends AbstractExchangeReader
{
    public function __construct(ExchangeContextFactory $factory)
    {
        parent::__construct($factory, 'attribute', 'export');
    }
}
