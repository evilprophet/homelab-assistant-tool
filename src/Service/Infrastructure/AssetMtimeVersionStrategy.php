<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Infrastructure;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

class AssetMtimeVersionStrategy implements VersionStrategyInterface
{
    protected array $versionsByPath = [];

    public function __construct(
        protected string $applicationDirectory
    ) {
    }

    public function getVersion(string $path): string
    {
        return $this->versionsByPath[$path] ??= $this->resolveVersion($path);
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);

        return $version === '' ? $path : sprintf('%s?v=%s', $path, $version);
    }

    protected function resolveVersion(string $path): string
    {
        $absolutePath = sprintf('%s/public/%s', rtrim($this->applicationDirectory, '/\\'), ltrim($path, '/'));
        $modifiedAt = is_file($absolutePath) ? filemtime($absolutePath) : false;

        return $modifiedAt === false ? '' : dechex($modifiedAt);
    }
}
