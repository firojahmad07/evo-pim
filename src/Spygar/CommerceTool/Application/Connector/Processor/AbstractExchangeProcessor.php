<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Processor;

use Akeneo\Tool\Component\Batch\Item\DataInvalidItem;
use Akeneo\Tool\Component\Batch\Item\InvalidItemException;
use Akeneo\Tool\Component\Batch\Item\ItemProcessorInterface;
use Spygar\CommerceTool\Application\Connector\Exchange\ExchangeEnvelope;

abstract class AbstractExchangeProcessor implements ItemProcessorInterface
{
    public function process($item): mixed
    {
        if (!$item instanceof ExchangeEnvelope) {
            throw new InvalidItemException(
                'The CommerceTool item cannot be processed.',
                new DataInvalidItem(['item' => \is_object($item) ? $item::class : \gettype($item)])
            );
        }

        return $item;
    }
}
