<?php

declare(strict_types=1);

namespace Spygar\Core\Connector;

/**
 * One entry in the sidebar shown while a connector is open.
 */
final class ConnectorMenuItem
{
    public function __construct(
        private string $code,
        private string $label,
        private string $route,
        private int $position = 100,
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

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
