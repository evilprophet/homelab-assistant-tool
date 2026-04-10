<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Exception;

use EvilStudio\HAT\Contract\DeviceAction;
use RuntimeException;

class UnsupportedDeviceAction extends RuntimeException
{
    public static function forPlatform(DeviceAction $action, string $platform): self
    {
        return new self(
            sprintf("%s action is not supported on '%s' device.", $action->label(), $platform)
        );
    }
}
