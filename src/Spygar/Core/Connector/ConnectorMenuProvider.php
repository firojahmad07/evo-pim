<?php

declare(strict_types=1);

namespace Spygar\Core\Connector;

/**
 * Implemented by an installed bundle that adds its own sidebar entries.
 */
interface ConnectorMenuProvider
{
    /**
     * @return list<ConnectorMenuItem>
     */
    public function getConnectorMenu(): array;
}
