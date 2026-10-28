<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Writer;

use Akeneo\Tool\Component\Batch\Item\ItemWriterInterface;
use Spygar\CommerceTool\Application\Connector\Exchange\ExchangeEnvelope;

abstract class AbstractExchangeWriter implements ItemWriterInterface
{
    public function __construct(private object $transfer, private string $method)
    {
    }

    public function write(array $items): void
    {
        foreach ($items as $item) {
            if (!$item instanceof ExchangeEnvelope) {
                continue;
            }
            $this->transfer->{$this->method}($item->context, $item->reporter);
        }
    }
}
