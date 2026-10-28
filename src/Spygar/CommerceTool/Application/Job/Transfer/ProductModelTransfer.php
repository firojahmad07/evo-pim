<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job\Transfer;

use Akeneo\Pim\Enrichment\Bundle\Doctrine\ORM\Repository\ProductModelRepository;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductInterface;
use Akeneo\Pim\Enrichment\Component\Product\Model\ProductModelInterface;
use Akeneo\Pim\Structure\Bundle\Doctrine\ORM\Repository\FamilyVariantRepository;
use Akeneo\Pim\Structure\Component\Model\FamilyVariantInterface;
use Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface;
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

final class ProductModelTransfer
{
    public function __construct(
        private Connection $connection,
        private ProductModelRepository $productModels,
        private FamilyVariantRepository $familyVariants,
        private SimpleFactoryInterface $factory,
        private ObjectUpdaterInterface $updater,
        private SaverInterface $saver,
        private EntityManagerInterface $entityManager,
        private ProductTypeRegistry $productTypes,
        private ProductPayload $payload,
        private CommerceToolGateway $gateway,
        private CommerceToolProductWriter $writer,
        private ImportedValues $imported,
        private ProductTransfer $products,
        private AkeneoPersister $persister,
    ) {
    }

    public function export(TransferContext $context, StepReporter $reporter): void
    {
        $definitions = $this->productTypes->definitions($this->productTypes->load($context->credential, $context->token));
        $seen = [];
        foreach ($this->codes() as $code) {
            $reporter->read();
            $model = $this->productModels->findOneByIdentifier($code);
            if (!$model instanceof ProductModelInterface || null !== $model->getParent()) {
                continue;
            }
            $key = ResourceKey::commerceTool($code);
            if (null === $key) {
                $reporter->skip('The product model code cannot be used as a CommerceTool key.', ['code' => $code]);
                continue;
            }
            $draft = $this->payload->collect($context, $model, $definitions, $key, false);
            $variants = [];
            foreach ($this->variantProducts($model) as $product) {
                $identifier = $product->getIdentifier();
                if (!\is_string($identifier) || '' === $identifier) {
                    continue;
                }
                $variantDraft = $this->payload->collect($context, $product, $definitions, $key, true);
                $draft = $this->payload->merge($draft, $variantDraft);
                $variants[] = ['sku' => $identifier, 'attributes' => $variantDraft['attributes']];
                if ($this->entityManager->contains($product)) {
                    $this->entityManager->detach($product);
                }
            }
            if ([] === $variants) {
                $variants[] = ['sku' => $code, 'attributes' => $draft['attributes']];
            }
            $this->notices($reporter, $draft['notices'], $seen);
            $this->writer->upsert($context->credential, $context->token, $key, $draft, $variants, true, $reporter, ['code' => $code]);
            if ($this->entityManager->contains($model)) {
                $this->entityManager->detach($model);
            }
        }
    }

    public function import(TransferContext $context, StepReporter $reporter): void
    {
        $categoryIds = [];
        foreach ($this->gateway->pages($context->credential, $context->token, 'categories') as $category) {
            if (isset($category['id'], $category['key']) && \is_string($category['id']) && \is_string($category['key'])) {
                $categoryIds[$category['id']] = $category['key'];
            }
        }

        foreach ($this->gateway->pages($context->credential, $context->token, 'products') as $product) {
            $current = $product['masterData']['current'] ?? $product['masterData']['staged'] ?? [];
            if (!\is_array($current)) {
                continue;
            }
            $variants = $current['variants'] ?? [];
            if (!\is_array($variants) || [] === $variants) {
                continue;
            }
            $reporter->read();
            $key = isset($product['key']) && \is_string($product['key']) ? $product['key'] : '';
            $code = ResourceKey::akeneo($key);
            if (null === $code) {
                $reporter->skip('The CommerceTool product key cannot be stored as a product model.', ['code' => $key]);
                continue;
            }
            $attributeNames = $this->attributeNames($current);
            $familyVariant = $this->familyVariant($attributeNames);
            if (!$familyVariant instanceof FamilyVariantInterface) {
                $reporter->skip('No one-level family variant matches the CommerceTool variant attributes.', ['code' => $code]);
                continue;
            }
            if (!$this->storeModel($context, $reporter, $product, $current, $categoryIds, $code, $familyVariant)) {
                continue;
            }
            $variantAttributeCodes = $this->variantAttributeCodes($familyVariant);
            $children = array_merge(
                [\is_array($current['masterVariant'] ?? null) ? $current['masterVariant'] : []],
                $variants
            );
            foreach ($children as $child) {
                if (!\is_array($child)) {
                    continue;
                }
                $sku = isset($child['sku']) && \is_string($child['sku']) ? $child['sku'] : '';
                $skuCode = ResourceKey::akeneo($sku) ?? $sku;
                if (1 !== preg_match('/^[a-zA-Z0-9_-]{1,255}$/', $skuCode)) {
                    $reporter->skip('A variant SKU cannot be stored in Akeneo.', ['code' => $code]);
                    continue;
                }
                $extracted = $this->imported->extract(
                    $context,
                    $current,
                    $categoryIds,
                    \is_array($child['attributes'] ?? null) ? $child['attributes'] : []
                );
                $extracted['values'] = $this->imported->only($extracted['values'], $variantAttributeCodes);
                $this->products->store($reporter, $skuCode, $extracted, $this->enabled($product), $code);
            }
        }
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $current
     * @param array<string, string> $categoryIds
     */
    private function storeModel(TransferContext $context, StepReporter $reporter, array $product, array $current, array $categoryIds, string $code, FamilyVariantInterface $familyVariant): bool
    {
        $extracted = $this->imported->extract(
            $context,
            $current,
            $categoryIds,
            \is_array($current['masterVariant']['attributes'] ?? null) ? $current['masterVariant']['attributes'] : []
        );
        $common = [];
        foreach ($familyVariant->getCommonAttributes() as $attribute) {
            $common[] = $attribute->getCode();
        }
        $extracted['values'] = $this->imported->only($extracted['values'], $common);
        $existing = $this->productModels->findOneByIdentifier($code);
        $isNew = !$existing instanceof ProductModelInterface;
        $model = $isNew ? $this->factory->create() : $existing;
        $data = [
            'code' => $code,
            'categories' => $extracted['categories'],
            'values' => $extracted['values'],
        ];
        if ($isNew) {
            $data['family_variant'] = $familyVariant->getCode();
        }
        try {
            $this->updater->update($model, $data);
        } catch (\Throwable $exception) {
            $reporter->skip($exception->getMessage(), ['code' => $code]);

            return false;
        }

        return $this->persister->save($model, $this->saver, $reporter, ['code' => $code], $isNew);
    }

    /**
     * @param list<string> $attributeNames
     */
    private function familyVariant(array $attributeNames): ?FamilyVariantInterface
    {
        $best = null;
        $bestScore = -1;
        foreach ($this->familyVariants->findAll() as $familyVariant) {
            if (!$familyVariant instanceof FamilyVariantInterface || 1 !== $familyVariant->getVariantAttributeSets()->count()) {
                continue;
            }
            $axes = $this->variantAttributeCodes($familyVariant);
            if ([] === $axes) {
                continue;
            }
            $matched = 0;
            foreach ($axes as $axis) {
                if (\in_array($axis, $attributeNames, true)) {
                    ++$matched;
                }
            }
            if ($matched > $bestScore) {
                $best = $familyVariant;
                $bestScore = $matched;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    /**
     * @return list<string>
     */
    private function variantAttributeCodes(FamilyVariantInterface $familyVariant): array
    {
        $codes = [];
        foreach ($familyVariant->getVariantAttributeSets() as $set) {
            foreach ($set->getAttributes() as $attribute) {
                $codes[] = $attribute->getCode();
            }
        }

        return $codes;
    }

    /**
     * @param array<string, mixed> $current
     *
     * @return list<string>
     */
    private function attributeNames(array $current): array
    {
        $names = [];
        $variants = array_merge(
            [\is_array($current['masterVariant'] ?? null) ? $current['masterVariant'] : []],
            \is_array($current['variants'] ?? null) ? $current['variants'] : []
        );
        foreach ($variants as $variant) {
            if (!\is_array($variant)) {
                continue;
            }
            foreach ($variant['attributes'] ?? [] as $attribute) {
                if (\is_array($attribute) && isset($attribute['name']) && \is_string($attribute['name'])) {
                    $code = ResourceKey::akeneo($attribute['name']) ?? $attribute['name'];
                    $names[$code] = $code;
                }
            }
        }

        return array_values($names);
    }

    /**
     * @return list<ProductInterface>
     */
    private function variantProducts(ProductModelInterface $model): array
    {
        $products = [];
        foreach ($model->getProducts() as $product) {
            if ($product instanceof ProductInterface) {
                $products[] = $product;
            }
        }
        foreach ($model->getProductModels() as $child) {
            if ($child instanceof ProductModelInterface) {
                $products = array_merge($products, $this->variantProducts($child));
            }
        }

        return $products;
    }

    /**
     * @return list<string>
     */
    private function codes(): array
    {
        try {
            $codes = $this->connection->fetchFirstColumn(
                'SELECT code FROM pim_catalog_product_model WHERE parent_id IS NULL ORDER BY code ASC'
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Akeneo product models could not be read: '.mb_substr($exception->getMessage(), 0, 180));
        }

        return array_values(array_filter($codes, 'is_string'));
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
     */
    private function enabled(array $product): bool
    {
        $published = $product['masterData']['published'] ?? false;

        return true === $published || (\is_array($published) && [] !== $published);
    }
}
