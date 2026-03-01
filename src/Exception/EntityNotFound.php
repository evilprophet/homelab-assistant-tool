<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Exception;

use RuntimeException;

class EntityNotFound extends RuntimeException
{
    public static function forField(string $entityName, string $fieldName, string|int $value): self
    {
        return new self(sprintf("%s with %s '%s' not found.", $entityName, $fieldName, $value));
    }
}
