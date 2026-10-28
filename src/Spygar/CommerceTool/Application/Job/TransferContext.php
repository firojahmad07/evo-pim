<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Spygar\CommerceTool\Domain\Model\Credential;

final class TransferContext
{
    /**
     * @param list<string>                       $locales
     * @param list<string>                       $currencies
     * @param array<string, string>              $localeMap
     * @param array<string, string>              $currencyMap
     * @param array<string, string>              $channelMap
     * @param list<array<string, string|null>>   $mappings
     */
    public function __construct(
        public readonly Credential $credential,
        public readonly string $token,
        public readonly array $locales,
        public readonly array $currencies,
        public readonly array $localeMap,
        public readonly array $currencyMap,
        public readonly array $channelMap,
        public readonly array $mappings,
        public readonly StepExecution $step,
    ) {
    }

    public function scope(): ?string
    {
        foreach ($this->channelMap as $akeneoChannel) {
            if ('' !== $akeneoChannel) {
                return $akeneoChannel;
            }
        }

        return null;
    }
}
