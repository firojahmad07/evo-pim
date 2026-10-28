<?php

declare(strict_types=1);

namespace Spygar\CommerceTool\Infrastructure\Symfony;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Spygar\Core\Connector\ConnectorMenuItem;
use Spygar\Core\Connector\ConnectorMenuProvider;
use Spygar\Core\Connector\CustomBundleDescriptor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SpygarCommerceToolBundle extends Bundle implements CustomBundleDescriptor, ConnectorMenuProvider
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            DoctrineOrmMappingsPass::createYamlMappingDriver(
                [realpath(__DIR__.'/Resources/config/model/doctrine') => 'Spygar\CommerceTool\Domain\Model'],
                ['doctrine.orm.entity_manager'],
                false
            )
        );
    }

    public function getConnectorCode(): string
    {
        return 'commercetool';
    }

    public function getConnectorLabel(): string
    {
        return 'CommerceTool';
    }

    public function getConnectorLogo(): ?string
    {
        return '/bundles/spygarcommercetool/images/logo.svg';
    }

    public function getConnectorRoute(): ?string
    {
        return null;
    }

    public function getConnectorMenu(): array
    {
        return [
            new ConnectorMenuItem(
                'credentials',
                'spygar_commercetool.menu.credentials',
                'spygar_commercetool_credential_index',
                10
            ),
            new ConnectorMenuItem(
                'attribute_mapping',
                'spygar_commercetool.menu.attribute_mapping',
                'spygar_commercetool_attribute_mapping_index',
                20
            ),
            new ConnectorMenuItem(
                'settings',
                'spygar_commercetool.menu.settings',
                'spygar_commercetool_settings_index',
                30
            ),
        ];
    }
}
