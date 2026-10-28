<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Domain\Model;

class AttributeMapping
{
    public const TYPE_FIELD = 'field';

    public const TYPE_IMAGE = 'image';

    private ?int $id = null;

    private string $code = '';

    private string $akeneoAttribute = '';

    private string $commercetoolsAttribute = '';

    private ?string $variantAkeneoAttribute = null;

    private string $mappingType = self::TYPE_FIELD;

    private int $position = 0;

    private ?string $locale = null;

    private ?string $channel = null;

    private bool $active = true;

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

    public function getAkeneoAttribute(): string
    {
        return $this->akeneoAttribute;
    }

    public function setAkeneoAttribute(string $akeneoAttribute): void
    {
        $this->akeneoAttribute = $akeneoAttribute;
    }

    public function getCommercetoolsAttribute(): string
    {
        return $this->commercetoolsAttribute;
    }

    public function setCommercetoolsAttribute(string $commercetoolsAttribute): void
    {
        $this->commercetoolsAttribute = $commercetoolsAttribute;
    }

    public function getVariantAkeneoAttribute(): ?string
    {
        return $this->variantAkeneoAttribute;
    }

    public function setVariantAkeneoAttribute(?string $variantAkeneoAttribute): void
    {
        $this->variantAkeneoAttribute = $variantAkeneoAttribute;
    }

    public function getMappingType(): string
    {
        return $this->mappingType;
    }

    public function setMappingType(string $mappingType): void
    {
        $this->mappingType = $mappingType;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(?string $channel): void
    {
        $this->channel = $channel;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
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
