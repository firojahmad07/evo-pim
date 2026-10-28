<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Connector\Exchange;

use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Doctrine\DBAL\Connection;
use Spygar\CommerceTool\Application\CommerceToolClient;
use Spygar\CommerceTool\Application\CommerceToolConnectionException;
use Spygar\CommerceTool\Application\CredentialCatalogStore;
use Spygar\CommerceTool\Application\Job\LocaleBridge;
use Spygar\CommerceTool\Application\Job\MappingReader;
use Spygar\CommerceTool\Application\Job\StepReporter;
use Spygar\CommerceTool\Application\Job\TransferContext;
use Spygar\CommerceTool\Domain\Model\Credential;
use Spygar\CommerceTool\Infrastructure\Persistence\CredentialRepository;

final class ExchangeContextFactory
{
    public function __construct(
        private CredentialRepository $credentials,
        private CredentialCatalogStore $catalog,
        private CommerceToolClient $client,
        private Connection $connection,
        private LocaleBridge $locales,
        private MappingReader $mappings,
        private JobRepositoryInterface $jobRepository,
    ) {
    }

    public function open(StepExecution $step, string $entity, string $direction): ExchangeEnvelope
    {
        $name = $step->getJobExecution()->getJobInstance()->getJobName();
        if ($name !== 'commercetool_'.$entity.'_'.$direction) {
            throw new \RuntimeException('This CommerceTool job is not supported.');
        }

        $code = trim((string) $step->getJobParameters()->get('credential'));
        $credential = $this->credentials->findOneByIdentifier($code);
        if (!$credential instanceof Credential) {
            throw new \RuntimeException('Select a credential that exists.');
        }
        if (!$credential->isActive()) {
            throw new \RuntimeException('The selected credential is disabled.');
        }
        $this->assertSettings($entity, $direction);

        try {
            $session = $this->client->connect($credential);
        } catch (CommerceToolConnectionException $exception) {
            throw new \RuntimeException($exception->getMessage(), 0, $exception);
        }

        $present = $this->catalog->present($credential->getCode());
        $context = new TransferContext(
            $credential,
            $session['token'],
            $session['locales'],
            $session['currencies'],
            $this->locales->pairs($this->map($present['locale_mapping']), $session['locales'], $this->activatedLocales()),
            $this->map($present['currency_mapping']),
            $this->map($present['channel_mapping']),
            $this->mappings->active(),
            $step,
        );
        $reporter = new StepReporter($step, $this->jobRepository);
        $reporter->note('credential', $credential->getCode());

        return new ExchangeEnvelope($context, $reporter);
    }

    private function assertSettings(string $entity, string $direction): void
    {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT enabled, export_products, export_categories, import_products FROM spygar_commercetool_settings WHERE code = ?',
                ['default']
            );
        } catch (\Throwable) {
            return;
        }
        if (!\is_array($row)) {
            return;
        }
        if (1 !== (int) $row['enabled']) {
            throw new \RuntimeException('Enable the CommerceTool connector in Settings before running this job.');
        }
        if ('export' === $direction && 'category' === $entity && 1 !== (int) $row['export_categories']) {
            throw new \RuntimeException('Enable category export in CommerceTool settings before running this job.');
        }
        if ('export' === $direction && \in_array($entity, ['product', 'product_model'], true) && 1 !== (int) $row['export_products']) {
            throw new \RuntimeException('Enable product export in CommerceTool settings before running this job.');
        }
        if ('import' === $direction && \in_array($entity, ['product', 'product_model'], true) && 1 !== (int) $row['import_products']) {
            throw new \RuntimeException('Enable product import in CommerceTool settings before running this job.');
        }
    }

    /**
     * @return list<string>
     */
    private function activatedLocales(): array
    {
        try {
            $locales = $this->connection->fetchFirstColumn(
                'SELECT code FROM pim_catalog_locale WHERE is_activated = 1 ORDER BY code ASC'
            );
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_filter($locales, 'is_string'));
    }

    /**
     * @return array<string, string>
     */
    private function map(mixed $value): array
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }
        if (!\is_array($value)) {
            return [];
        }
        $map = [];
        foreach ($value as $key => $item) {
            if (\is_string($key) && \is_string($item) && '' !== $key && '' !== $item) {
                $map[$key] = $item;
            }
        }

        return $map;
    }
}
