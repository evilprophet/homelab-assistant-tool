<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Support;

use EvilStudio\HAT\Contract\DevicePlatform;
use Symfony\Component\Console\Style\SymfonyStyle;

trait DevicePlatformInputTrait
{
    protected function normalizePlatform(string $platform): string
    {
        return strtolower(trim($platform));
    }

    protected function isPlatformSupported(string $platform, SymfonyStyle $io): bool
    {
        if (DevicePlatform::tryFrom($platform) !== null) {
            return true;
        }

        $io->error(
            sprintf(
                "Unsupported platform '%s'. Allowed values: %s.",
                $platform,
                implode(', ', DevicePlatform::values())
            )
        );

        return false;
    }
}
