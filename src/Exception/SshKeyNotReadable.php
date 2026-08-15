<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Exception;

use RuntimeException;

class SshKeyNotReadable extends RuntimeException
{
    public static function forPath(string $sshKeyPath): self
    {
        return new self(sprintf("SSH key '%s' is missing or not readable.", $sshKeyPath));
    }
}
