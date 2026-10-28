<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job\Transfer;

use Akeneo\Pim\Enrichment\Bundle\Doctrine\ORM\Repository\ProductRepository;
use Akeneo\Pim\Enrichment\Component\Product\Builder\ProductBuilder;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Spygar\CommerceTool\Application\Job\AkeneoPersister;
use Spygar\CommerceTool\Application\Job\CommerceToolGateway;
use Spygar\CommerceTool\Application\Job\CommerceToolProductWriter;
use Spygar\CommerceTool\Application\Job\ImportedValues;
use Spygar\CommerceTool\Application\Job\ProductPayload;
use Spygar\CommerceTool\Application\Job\ProductTypeRegistry;
use Spygar\CommerceTool\Application\Job\ResourceKey;
use Spygar\CommerceTool\Application\Job\StepReporter;
use Spygar\CommerceTool\Application\Job\TransferContext;

final class ProductTransfer
{
    public function __construct(
        private Connection $connection,
        private ProductRepository $products,
        private ProductBuilder $builder,
        private ObjectUpdaterInterface $updater,
        private SaverInterface $saver,
        private EntityManagerInterface $entityManager,
        private ProductTypeRegistry $productTypes,
        private ProductPayload $payload,
        private CommerceToolGateway $gateway,
        private CommerceToolProductWriter $writer,
        private ImportedValues $imported,
        private AkeneoPersister $persister,
    ) {
    }

    public function export(TransferContext $context, StepReporter $reporter): void
    {
        $definitions = $this->productTypes->definitions($this->productTypes->load($context->credential, $context->token));
        $seen = [];
        foreach ($this->identifiers() as $identifier) {
            $reporter->read();
            $product = $this->products->findOneByIdentifier($identifier);
            if (!$product instanceof ProductInterface || null !== $product->getParent()) {
                continue;
            }
            $key = ResourceKey::commerceTool($identifier);
            if (null === $key) {
                $reporter->skip('The product identifier cannot be used as a CommerceTool key.', ['code' => $identifier]);
                continue;
            }
            $draft = $this->payload->collect($context, $product, $definitions, $key, false);
            $this->notices($reporter, $draft['notices'], $seen);
            $this->writer->upsert(
                $context->credential,
                $context->token,
                $key,
                $draft,
                [['sku' => $identifier, 'attributes' => $draft['attributes']]],
                $product->isEnabled(),
                $reporter,
                ['code' => $identifier]
            );
            if ($this->entityManager->contains($product)) {
                $this->entityManager->detach($product);
            }
        }
    }

    public function import(TransferContext $context, StepReporter $reporter): void
    {
        $categories = $this->categoryIds($context);
        foreach ($this->productPages($context) as $product) {
            $current = $this->current($product);
            $variants = $current['variants'] ?? [];
            if (\is_array($variants) && [] !== $variants) {
                continue;
            }
            $reporter->read();
            $sku = $this->sku($product, $current);
            if (null === $sku) {
                $reporter->skip('The CommerceTool product has no SKU.', ['code' => (string) ($product['key'] ?? '')]);
                continue;
            }
            $extracted = $this->imported->extract(
                $context,
                $current,
                $categories,
                \is_array($current['masterVariant']['attributes'] ?? null) ? $current['masterVariant']['attributes'] : []
            );
            $this->store($reporter, $sku, $extracted, $this->enabled($product), null);
        }
    }

    /**
     * @param array{categories: list<string>, values: array<string, list<array{locale: ?string, scope: ?string, data: mixed}>>} $extracted
     */
    public function store(StepReporter $reporter, string $sku, array $extracted, bool $enabled, ?string $parent): void
    {
        $existing = $this->products->findOneByIdentifier($sku);
        $isNew = !$existing instanceof ProductInterface;
        $product = $isNew ? $this->builder->createProduct($sku) : $existing;
        $data = [
            'identifier' => $sku,
            'enabled' => $enabled,
            'categories' => $extracted['categories'],
            'values' => $extracted['values'],
        ];
        if (null !== $parent) {
            $data['parent'] = $parent;
        }
        try {
            $this->updater->update($product, $data);
        } catch (\Throwable $exception) {
            $reporter->skip($exception->getMessage(), ['code' => $sku]);

            return;
        }
        $this->persister->save($product, $this->saver, $reporter, ['code' => $sku], $isNew);
    }

    /**
     * @return list<string>
     */
    private function identifiers(): array
    {
        try {
            $identifiers = $this->connection->fetchFirstColumn(
                "SELECT identifier FROM pim_catalog_product WHERE product_model_id IS NULL AND identifier IS NOT NULL AND identifier <> '' ORDER BY identifier ASC"
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Akeneo products could not be read: '.mb_substr($exception->getMessage(), 0, 180));
        }

        return array_values(array_filter($identifiers, 'is_string'));
    }

    /**
     * @return array<string, string>
     */
    private function categoryIds(TransferContext $context): array
    {
        $ids = [];
        foreach ($this->productPages($context, 'categories') as $category) {
            if (isset($category['id'], $category['key']) && \is_string($category['id']) && \is_string($category['key'])) {
                $ids[$category['id']] = $category['key'];
            }
        }

        return $ids;
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    private function productPages(TransferContext $context, string $path = 'products'): \Generator
    {
        yield from $this->gateway->pages($context->credential, $context->token, $path);
    }

    /**
     * @param array<string, string> $notices
     * @param array<string, true>   $seen
     */
    private function notices(StepReporter $reporter, array $notices, array &$seen): void
    {
        foreach ($notices as $code => $message) {
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $reporter->warn($message, ['code' => $code]);
        }
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return array<string, mixed>
     */
    private function current(array $product): array
    {
        $current = $product['masterData']['current'] ?? $product['masterData']['staged'] ?? [];

        return \is_array($current) ? $current : [];
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $current
     */
    private function sku(array $product, array $current): ?string
    {
        $sku = $current['masterVariant']['sku'] ?? $product['key'] ?? null;
        if (!\is_string($sku) || '' === trim($sku)) {
            return null;
        }
        $code = ResourceKey::akeneo($sku) ?? $sku;

        return 1 === preg_match('/^[a-zA-Z0-9_-]{1,255}$/', $code) ? $code : null;
    }

    /**
     * @param array<string, mixed> $product
     */
    private function enabled(array $product): bool
    {
        $published = $product['masterData']['published'] ?? false;

        return true === $published || (\is_array($published) && [] !== $published);
    }
}
