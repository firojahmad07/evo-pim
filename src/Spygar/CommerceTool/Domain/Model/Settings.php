<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Domain\Model;

class Settings
{
    public const CODE = 'default';

    private ?int $id = null;

    private string $code = self::CODE;

    private bool $enabled = false;

    private bool $exportProducts = false;

    private bool $exportCategories = false;

    private bool $importProducts = false;

    private bool $debug = false;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isExportProducts(): bool
    {
        return $this->exportProducts;
    }

    public function setExportProducts(bool $exportProducts): void
    {
        $this->exportProducts = $exportProducts;
    }

    public function isExportCategories(): bool
    {
        return $this->exportCategories;
    }

    public function setExportCategories(bool $exportCategories): void
    {
        $this->exportCategories = $exportCategories;
    }

    public function isImportProducts(): bool
    {
        return $this->importProducts;
    }

    public function setImportProducts(bool $importProducts): void
    {
        $this->importProducts = $importProducts;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
