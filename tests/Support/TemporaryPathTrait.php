<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Support;

use Symfony\Component\Filesystem\Filesystem;

trait TemporaryPathTrait
{
    protected array $temporaryPaths = [];

    protected function createTemporaryPath(string $prefix): string
    {
        $path = sprintf('%s/%s%s', sys_get_temp_dir(), $prefix, str_replace('.', '', uniqid('', true)));
        $this->temporaryPaths[] = $path;

        return $path;
    }

    protected function removeTemporaryPaths(): void
    {
        if ($this->temporaryPaths === []) {
            return;
        }

        (new Filesystem())->remove($this->temporaryPaths);
        $this->temporaryPaths = [];
    }
}
