<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Exception;

use RuntimeException;

class EntityAlreadyExists extends RuntimeException
{
    public static function forField(string $entityName, string $fieldName, string $value): self
    {
        return new self(sprintf("%s with %s '%s' already exists.", $entityName, $fieldName, $value));
    }
}
