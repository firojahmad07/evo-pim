<?php

declare(strict_types=1);

namespace Spygar\Core\Connector;

/**
 * One installed custom bundle shown on the Connectors page.
 */
final class CustomBundle
{
    /**
     * @param list<ConnectorMenuItem> $menu
     */
    public function __construct(
        private string $code,
        private string $label,
        private ?string $logo = null,
        private ?string $route = null,
        private array $menu = [],
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    /**
     * @return list<ConnectorMenuItem>
     */
    public function getMenu(): array
    {
        return $this->menu;
    }
}
