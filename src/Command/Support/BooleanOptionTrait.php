<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Support;

use Symfony\Component\Console\Style\SymfonyStyle;

trait BooleanOptionTrait
{
    protected function parseBoolOption(mixed $value, string $optionName, SymfonyStyle $io): ?bool
    {
        $normalized = strtolower(trim((string)$value));
        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'off'], true)) {
            return false;
        }

        $io->error(sprintf('Option %s must be a boolean value (1/0, true/false, yes/no).', $optionName));

        return null;
    }
}
