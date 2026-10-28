<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Application\Job\Transfer;

use Akeneo\Category\Infrastructure\Component\Classification\Model\CategoryInterface;
use Akeneo\Category\Infrastructure\Doctrine\ORM\Repository\CategoryRepository;
use Akeneo\Channel\Infrastructure\Doctrine\Repository\ChannelRepository;
use Akeneo\Tool\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Tool\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Spygar\CommerceTool\Application\Job\AkeneoPersister;
use Spygar\CommerceTool\Application\Job\CommerceToolGateway;
use Spygar\CommerceTool\Application\Job\LocaleBridge;
use Spygar\CommerceTool\Application\Job\ResourceKey;
use Spygar\CommerceTool\Application\Job\StepReporter;
use Spygar\CommerceTool\Application\Job\TransferContext;

final class CategoryTransfer
{
    public function __construct(
        private CategoryRepository $categories,
        private SimpleFactoryInterface $factory,
        private ObjectUpdaterInterface $updater,
        private \Akeneo\Tool\Component\StorageUtils\Saver\SaverInterface $saver,
        private ChannelRepository $channels,
        private CommerceToolGateway $gateway,
        private LocaleBridge $locales,
        private AkeneoPersister $persister,
    ) {
    }

    public function export(TransferContext $context, StepReporter $reporter): void
    {
        foreach ($this->categories->findBy([], ['root' => 'ASC', 'left' => 'ASC']) as $category) {
            if (!$category instanceof CategoryInterface || null === $category->getParent()) {
                continue;
            }
            $reporter->read();
            $key = ResourceKey::commerceTool($category->getCode());
            if (null === $key) {
                $reporter->skip('The category code cannot be used as a CommerceTool key.', ['code' => $category->getCode()]);
                continue;
            }

            try {
                $this->upsert($context, $reporter, $category, $key);
            } catch (\RuntimeException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                $reporter->skip($exception->getMessage(), ['code' => $category->getCode()]);
            }
        }
    }

    public function import(TransferContext $context, StepReporter $reporter): void
    {
        $root = $this->treeRoot();
        if (null === $root) {
            throw new \RuntimeException('Akeneo has no category tree to receive CommerceTool categories.');
        }

        $rows = [];
        $idToKey = [];
        foreach ($this->gateway->pages($context->credential, $context->token, 'categories') as $row) {
            $key = isset($row['key']) && \is_string($row['key']) ? $row['key'] : '';
            $id = isset($row['id']) && \is_string($row['id']) ? $row['id'] : '';
            if ('' === $key || '' === $id) {
                continue;
            }
            $rows[] = $row;
            $idToKey[$id] = $key;
        }

        $pending = [];
        foreach ($rows as $row) {
            $parentId = \is_array($row['parent'] ?? null) ? (string) ($row['parent']['id'] ?? '') : '';
            $pending[$row['key']] = [
                'row' => $row,
                'parent' => '' !== $parentId ? ($idToKey[$parentId] ?? null) : null,
            ];
        }

        $imported = [];
        for ($pass = 0; $pass < 30 && [] !== $pending; ++$pass) {
            $progress = false;
            foreach ($pending as $key => $item) {
                $parentKey = $item['parent'];
                if (null !== $parentKey && !isset($imported[$parentKey])) {
                    continue;
                }
                $progress = true;
                unset($pending[$key]);
                $reporter->read();
                $code = ResourceKey::akeneo((string) $key);
                if (null === $code || $code === $root) {
                    $reporter->skip('The CommerceTool category key cannot be stored as an Akeneo category.', ['code' => (string) $key]);
                    continue;
                }
                $parentCode = null === $parentKey ? $root : ($imported[$parentKey] ?? $root);
                if ($this->store($context, $reporter, $code, $parentCode, $item['row'])) {
                    $imported[$key] = $code;
                }
            }
            if (!$progress) {
                break;
            }
        }

        foreach ($pending as $key => $item) {
            $reporter->read();
            $reporter->skip('The parent category was not imported.', ['code' => (string) $key]);
        }
    }

    private function upsert(TransferContext $context, StepReporter $reporter, CategoryInterface $category, string $key): void
    {
        $labels = [];
        foreach ($category->getTranslations() as $translation) {
            $locale = $translation->getLocale();
            $label = method_exists($translation, 'getLabel') ? $translation->getLabel() : null;
            if (\is_string($locale) && \is_string($label) && '' !== trim($label)) {
                $labels[$locale] = trim($label);
            }
        }
        $name = $this->locales->toCommerceTool($labels, $context->localeMap, $context->locales, $category->getCode());
        $slug = [];
        foreach ($name as $locale => $ignored) {
            $slug[$locale] = $key;
        }
        $parent = $category->getParent();
        $parentKey = null !== $parent && null !== $parent->getParent() ? ResourceKey::commerceTool((string) $parent->getCode()) : null;
        $item = ['code' => $category->getCode()];
        $existing = $this->gateway->getOne($context->credential, $context->token, 'categories/key='.rawurlencode($key));
        if (null === $existing) {
            $payload = ['key' => $key, 'name' => $name, 'slug' => $slug];
            if (null !== $parentKey) {
                $payload['parent'] = ['typeId' => 'category', 'key' => $parentKey];
            }
            $created = $this->gateway->post($context->credential, $context->token, 'categories', ['json' => $payload]);
            if (!\in_array($created['status'], [200, 201], true)) {
                $reporter->skip($this->gateway->failure($context->credential, $created, 'CommerceTool rejected the category.'), $item);

                return;
            }
            $reporter->created();

            return;
        }

        $actions = [
            ['action' => 'changeName', 'name' => $name],
            ['action' => 'changeSlug', 'slug' => $slug],
        ];
        if (null !== $parentKey) {
            $actions[] = ['action' => 'changeParent', 'parent' => ['typeId' => 'category', 'key' => $parentKey]];
        }
        $updated = $this->gateway->post($context->credential, $context->token, 'categories/key='.rawurlencode($key), [
            'json' => ['version' => (int) ($existing['version'] ?? 1), 'actions' => $actions],
        ]);
        if (!\in_array($updated['status'], [200, 201], true)) {
            $reporter->skip($this->gateway->failure($context->credential, $updated, 'CommerceTool rejected the category update.'), $item);

            return;
        }
        $reporter->updated();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function store(TransferContext $context, StepReporter $reporter, string $code, string $parentCode, array $row): bool
    {
        $labels = [];
        $name = \is_array($row['name'] ?? null) ? $row['name'] : [];
        foreach ($context->localeMap as $ctLocale => $akeneoLocale) {
            $label = $name[$ctLocale] ?? null;
            if (\is_string($label) && '' !== trim($label)) {
                $labels[$akeneoLocale] = trim($label);
            }
        }
        if ([] === $labels) {
            foreach ($name as $label) {
                $locale = array_values($context->localeMap)[0] ?? null;
                if (\is_string($locale) && \is_string($label) && '' !== trim($label)) {
                    $labels[$locale] = trim($label);
                    break;
                }
            }
        }

        $existing = $this->categories->findOneByIdentifier($code);
        $isNew = !$existing instanceof CategoryInterface;
        $category = $isNew ? $this->factory->create() : $existing;
        $data = ['code' => $code, 'labels' => $labels];
        if ($isNew || null !== $category->getParent()) {
            $data['parent'] = $parentCode;
        }

        try {
            $this->updater->update($category, $data);
        } catch (\Throwable $exception) {
            $reporter->skip($exception->getMessage(), ['code' => $code]);

            return false;
        }

        return $this->persister->save($category, $this->saver, $reporter, ['code' => $code], $isNew);
    }

    private function treeRoot(): ?string
    {
        $channels = $this->channels->findBy([], ['code' => 'ASC'], 1);
        $category = isset($channels[0]) ? $channels[0]->getCategory() : null;
        if ($category instanceof CategoryInterface && '' !== $category->getCode()) {
            return $category->getCode();
        }

        foreach ($this->categories->findBy(['parent' => null], ['code' => 'ASC'], 1) as $root) {
            return $root->getCode();
        }

        return null;
    }
}
