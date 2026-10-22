<?php

declare(strict_types=1);

namespace Spygar\Core\Connector;

/**
 * Implemented by a Symfony bundle that should describe itself on the Connectors page.
 */
interface CustomBundleDescriptor
{
    public function getConnectorCode(): string;

    public function getConnectorLabel(): string;

    public function getConnectorLogo(): ?string;

    public function getConnectorRoute(): ?string;
}
