<?php

declare(strict_types=1);

namespace Spygar\Core\Connector;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Installed bundles that live in this project and are not part of Akeneo, Oro, or FOS.
 * A service tagged spygar.custom_bundle replaces the discovered entry that shares its code.
 */
final class InstalledCustomBundleRegistry
{
    /**
     * @param iterable<CustomBundle> $taggedBundles
     */
    public function __construct(
        private KernelInterface $kernel,
        private string $projectDir,
        private iterable $taggedBundles,
    ) {
    }

    /**
     * @return list<CustomBundle>
     */
    public function all(): array
    {
        $bundles = [];
        foreach ($this->taggedBundles as $bundle) {
            if (!$bundle instanceof CustomBundle) {
                continue;
            }
            $bundles[$bundle->getCode()] = $bundle;
        }

        $projectSrc = realpath($this->projectDir.'/src');
        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$this->isInstalledCustomBundle($bundle, $projectSrc)) {
                continue;
            }

            if ($bundle instanceof CustomBundleDescriptor) {
                $code = $bundle->getConnectorCode();
                if (isset($bundles[$code])) {
                    continue;
                }
                $bundles[$code] = new CustomBundle(
                    $code,
                    $bundle->getConnectorLabel(),
                    $bundle->getConnectorLogo(),
                    $bundle->getConnectorRoute(),
                    $this->menuOf($bundle),
                );
                continue;
            }

            $code = $bundle->getName();
            if (isset($bundles[$code])) {
                continue;
            }

            $bundles[$code] = new CustomBundle(
                $code,
                $this->humanize($code),
                $this->findLogo($bundle),
                null,
                $this->menuOf($bundle),
            );
        }

        uasort(
            $bundles,
            static fn (CustomBundle $left, CustomBundle $right): int => strcasecmp($left->getLabel(), $right->getLabel())
        );

        return array_values($bundles);
    }

    private function isInstalledCustomBundle(BundleInterface $bundle, string|false $projectSrc): bool
    {
        $class = $bundle::class;
        if (
            str_starts_with($class, 'Akeneo\\')
            || str_starts_with($class, 'Oro\\')
            || str_starts_with($class, 'FOS\\')
            || str_starts_with($class, 'Spygar\\Core\\')
        ) {
            return false;
        }

        if (false === $projectSrc) {
            return false;
        }

        $bundlePath = realpath($bundle->getPath());

        return false !== $bundlePath && str_starts_with($bundlePath, $projectSrc.DIRECTORY_SEPARATOR);
    }

    /**
     * @return list<ConnectorMenuItem>
     */
    private function menuOf(BundleInterface $bundle): array
    {
        if (!$bundle instanceof ConnectorMenuProvider) {
            return [];
        }

        return array_values(array_filter(
            $bundle->getConnectorMenu(),
            static fn ($item): bool => $item instanceof ConnectorMenuItem
        ));
    }

    private function humanize(string $name): string
    {
        $name = (string) preg_replace('/Bundle$/', '', $name);

        return (string) preg_replace('/(?<!^)(?=[A-Z])/', ' ', $name);
    }

    private function findLogo(BundleInterface $bundle): ?string
    {
        foreach (['logo.svg', 'logo.png', 'logo.jpg', 'logo.jpeg'] as $file) {
            if (!is_file($bundle->getPath().'/Resources/public/images/'.$file)) {
                continue;
            }

            $publicName = strtolower((string) preg_replace('/bundle$/', '', $bundle->getName()));

            return sprintf('/bundles/%s/images/%s', $publicName, $file);
        }

        return null;
    }
}
