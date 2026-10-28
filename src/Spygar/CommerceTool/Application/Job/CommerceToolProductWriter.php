<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job;

use Spygar\CommerceTool\Domain\Model\Credential;

final class CommerceToolProductWriter
{
    public function __construct(private CommerceToolGateway $gateway)
    {
    }

    /**
     * @param array<string, mixed> $draft
     * @param list<array{sku: string, attributes: list<array{name: string, value: mixed}>}> $variants
     * @param array<string, scalar|null> $item
     */
    public function upsert(
        Credential $credential,
        string $token,
        string $key,
        array $draft,
        array $variants,
        bool $enabled,
        StepReporter $reporter,
        array $item,
    ): void {
        if ([] === $variants) {
            $reporter->skip('The product has no SKU to send.', $item);

            return;
        }
        $master = $variants[0];
        $existing = $this->gateway->getOne($credential, $token, 'products/key='.rawurlencode($key), [
            'expand' => 'masterData.current.categories[*]',
        ]);
        $isNew = null === $existing;
        if ($isNew) {
            $payload = [
                'key' => $key,
                'productType' => ['typeId' => 'product-type', 'key' => ProductTypeRegistry::KEY],
                'name' => $draft['name'],
                'slug' => $draft['slug'],
                'masterVariant' => [
                    'sku' => $master['sku'],
                    'attributes' => $master['attributes'],
                ],
            ];
            $variantKey = ResourceKey::commerceTool($master['sku']);
            if (null !== $variantKey) {
                $payload['masterVariant']['key'] = $variantKey;
            }
            if ([] !== $draft['description']) {
                $payload['description'] = $draft['description'];
            }
            if ([] !== $draft['categories']) {
                $payload['categories'] = array_map(
                    static fn (string $category): array => ['typeId' => 'category', 'key' => $category],
                    $draft['categories']
                );
            }
            $created = $this->gateway->post($credential, $token, 'products', ['json' => $payload]);
            if (!\in_array($created['status'], [200, 201], true)) {
                $reporter->skip($this->gateway->failure($credential, $created, 'CommerceTool rejected the product.'), $item);

                return;
            }
            $existing = $created['body'];
        }

        $actions = $isNew ? [] : $this->changes($existing, $draft, $master);
        foreach (\array_slice($variants, 1) as $variant) {
            if ($this->hasSku($existing, $variant['sku'])) {
                foreach ($variant['attributes'] as $attribute) {
                    $actions[] = ['action' => 'setAttribute', 'sku' => $variant['sku'], 'name' => $attribute['name'], 'value' => $attribute['value']];
                }
                continue;
            }
            $action = [
                'action' => 'addVariant',
                'sku' => $variant['sku'],
                'attributes' => $variant['attributes'],
            ];
            $variantKey = ResourceKey::commerceTool($variant['sku']);
            if (null !== $variantKey) {
                $action['key'] = $variantKey;
            }
            $actions[] = $action;
        }
        if ([] !== $draft['prices']) {
            $actions[] = ['action' => 'setPrices', 'sku' => $master['sku'], 'prices' => $draft['prices']];
        }
        $published = $this->isPublished($existing);
        if ($enabled && !$published) {
            $actions[] = ['action' => 'publish'];
        } elseif (!$enabled && $published) {
            $actions[] = ['action' => 'unpublish'];
        }
        $this->apply($credential, $token, $existing, $actions, $reporter, $item, 0);
        if ($isNew) {
            $reporter->created();
        } else {
            $reporter->updated();
        }
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $draft
     * @param array{sku: string, attributes: list<array{name: string, value: mixed}>} $master
     *
     * @return list<array<string, mixed>>
     */
    private function changes(array $product, array $draft, array $master): array
    {
        $actions = [
            ['action' => 'changeName', 'name' => $draft['name']],
            ['action' => 'changeSlug', 'slug' => $draft['slug']],
        ];
        if ([] !== $draft['description']) {
            $actions[] = ['action' => 'setDescription', 'description' => $draft['description']];
        }
        if ([] !== $draft['metaTitle']) {
            $actions[] = ['action' => 'setMetaTitle', 'metaTitle' => $draft['metaTitle']];
        }
        if ([] !== $draft['metaDescription']) {
            $actions[] = ['action' => 'setMetaDescription', 'metaDescription' => $draft['metaDescription']];
        }
        foreach ($master['attributes'] as $attribute) {
            $actions[] = ['action' => 'setAttribute', 'sku' => $master['sku'], 'name' => $attribute['name'], 'value' => $attribute['value']];
        }
        $present = [];
        foreach ($this->current($product)['categories'] ?? [] as $category) {
            if (!\is_array($category)) {
                continue;
            }
            $key = $category['obj']['key'] ?? $category['key'] ?? null;
            if (\is_string($key) && '' !== $key) {
                $present[$key] = true;
            }
        }
        foreach ($draft['categories'] as $category) {
            if (!isset($present[$category])) {
                $actions[] = ['action' => 'addToCategory', 'category' => ['typeId' => 'category', 'key' => $category]];
            }
        }

        return $actions;
    }

    /**
     * @param array<string, mixed>            $product
     * @param list<array<string, mixed>>      $actions
     * @param array<string, scalar|null>      $item
     *
     * @return array<string, mixed>
     */
    private function apply(Credential $credential, string $token, array $product, array $actions, StepReporter $reporter, array $item, int $retries): array
    {
        if ([] === $actions) {
            return $product;
        }
        $key = (string) ($product['key'] ?? '');
        $response = $this->gateway->post($credential, $token, 'products/key='.rawurlencode($key), [
            'json' => ['version' => (int) ($product['version'] ?? 1), 'actions' => array_values($actions)],
        ]);
        if (\in_array($response['status'], [200, 201], true)) {
            return $response['body'];
        }
        if (409 === $response['status'] && $retries < 1) {
            $reloaded = $this->gateway->getOne($credential, $token, 'products/key='.rawurlencode($key));
            if (null !== $reloaded) {
                return $this->apply($credential, $token, $reloaded, $actions, $reporter, $item, $retries + 1);
            }
        }
        if (1 === \count($actions)) {
            $reporter->warn($this->gateway->failure($credential, $response, 'CommerceTool rejected a product update.'), $item);

            return $product;
        }
        $mid = intdiv(\count($actions), 2);
        $product = $this->apply($credential, $token, $product, \array_slice($actions, 0, $mid), $reporter, $item, 0);

        return $this->apply($credential, $token, $product, \array_slice($actions, $mid), $reporter, $item, 0);
    }

    /**
     * @param array<string, mixed> $product
     */
    private function isPublished(array $product): bool
    {
        $published = $product['masterData']['published'] ?? false;

        return true === $published || (\is_array($published) && [] !== $published);
    }

    /**
     * @param array<string, mixed> $product
     */
    private function hasSku(array $product, string $sku): bool
    {
        $current = $this->current($product);
        if (($current['masterVariant']['sku'] ?? null) === $sku) {
            return true;
        }
        foreach ($current['variants'] ?? [] as $variant) {
            if (\is_array($variant) && ($variant['sku'] ?? null) === $sku) {
                return true;
            }
        }

        return false;
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
}
