<?php

declare(strict_types=1);

namespace Spygar\Core\Infrastructure\Symfony\Controller;

use Oro\Bundle\SecurityBundle\SecurityFacade;
use Spygar\Core\Connector\ConnectorMenuItem;
use Spygar\Core\Connector\CustomBundle;
use Spygar\Core\Connector\InstalledCustomBundleRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ConnectorsController
{
    public function __construct(
        private SecurityFacade $securityFacade,
        private InstalledCustomBundleRegistry $registry,
        private RouterInterface $router,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        if (
            !$this->securityFacade->isGranted('spygar_connectors_index')
            && !$this->securityFacade->isGranted('akeneo_connectivity_connection_manage_settings')
        ) {
            throw new AccessDeniedException();
        }

        $connectors = [];
        foreach ($this->registry->all() as $bundle) {
            $connectors[] = [
                'code' => $bundle->getCode(),
                'label' => $bundle->getLabel(),
                'logo' => $bundle->getLogo(),
                'menu' => $this->menu($bundle),
            ];
        }

        return new JsonResponse($connectors);
    }

    /**
     * @return list<array{code: string, label: string, route: string, position: int}>
     */
    private function menu(CustomBundle $bundle): array
    {
        $items = [];
        if (!$this->hasSettings($bundle)) {
            $items[] = [
                'code' => 'settings',
                'label' => 'spygar.connectors.menu.settings',
                'route' => 'spygar_connectors_edit',
                'position' => 10,
            ];
        }

        foreach ($bundle->getMenu() as $item) {
            if (!$item instanceof ConnectorMenuItem || !$this->routeExists($item->getRoute())) {
                continue;
            }

            $items[] = $this->normalize($item);
        }

        if (null !== $bundle->getRoute() && $this->routeExists($bundle->getRoute())) {
            $items[] = [
                'code' => 'open',
                'label' => $bundle->getLabel(),
                'route' => $bundle->getRoute(),
                'position' => 20,
            ];
        }

        $items = $this->unique($items);
        usort(
            $items,
            static fn (array $left, array $right): int => $left['position'] <=> $right['position']
        );

        return $items;
    }

    /**
     * @param list<array{code: string, label: string, route: string, position: int}> $items
     *
     * @return list<array{code: string, label: string, route: string, position: int}>
     */
    private function unique(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            $unique[$item['route']] = $item;
        }

        return array_values($unique);
    }

    /**
     * @return array{code: string, label: string, route: string, position: int}
     */
    private function normalize(ConnectorMenuItem $item): array
    {
        return [
            'code' => $item->getCode(),
            'label' => $item->getLabel(),
            'route' => $item->getRoute(),
            'position' => $item->getPosition(),
        ];
    }

    private function hasSettings(CustomBundle $bundle): bool
    {
        foreach ($bundle->getMenu() as $item) {
            if (
                $item instanceof ConnectorMenuItem
                && 'settings' === $item->getCode()
                && $this->routeExists($item->getRoute())
            ) {
                return true;
            }
        }

        return false;
    }

    private function routeExists(string $route): bool
    {
        return null !== $this->router->getRouteCollection()->get($route);
    }
}
