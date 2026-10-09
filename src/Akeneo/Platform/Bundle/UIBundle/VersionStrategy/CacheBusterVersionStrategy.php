<?php

namespace Akeneo\Platform\Bundle\UIBundle\VersionStrategy;

use Akeneo\Platform\Bundle\PimVersionBundle\VersionProviderInterface;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

class CacheBusterVersionStrategy implements VersionStrategyInterface
{
    /** @var VersionProviderInterface */
    protected $versionProvider;

    private string $projectDir;

    /**
     * @param VersionProviderInterface $versionProvider
     */
    public function __construct(VersionProviderInterface $versionProvider, string $projectDir)
    {
        $this->versionProvider = $versionProvider;
        $this->projectDir = $projectDir;
    }

    /**
     * @param string $path
     */
    public function getVersion($path): string
    {
        $publicFile = $this->projectDir.'/public/'.ltrim((string) $path, '/');
        $mtime = is_file($publicFile) ? (string) filemtime($publicFile) : '0';

        return $this->versionProvider->getPatch().'-'.$mtime;
    }

    /**
     * @param string $path
     */
    public function applyVersion($path): string
    {
        $versioned = sprintf('%s?%s', ltrim($path, DIRECTORY_SEPARATOR), md5($this->getVersion($path)));

        if ($path && DIRECTORY_SEPARATOR == $path[0]) {
            return DIRECTORY_SEPARATOR . $versioned;
        }

        return $versioned;
    }
}
