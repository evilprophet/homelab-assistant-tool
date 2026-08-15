<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Contract;

enum AuthMode: string
{
    case SIMPLE = 'simple';
    case OIDC = 'oidc';

    public static function values(): array
    {
        return array_map(static fn (self $mode): string => $mode->value, self::cases());
    }
}
