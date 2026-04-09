<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Contract;

enum DeviceAction: string
{
    case START = 'start';
    case STOP = 'stop';
    case SSH = 'ssh';

    public function label(): string
    {
        return match ($this) {
            self::START => 'Start',
            self::STOP => 'Stop',
            self::SSH => 'SSH',
        };
    }
}
