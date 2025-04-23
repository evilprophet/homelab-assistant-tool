<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Provider;

use EvilStudio\HAT\Helper\Configuration;

abstract class AbstractProvider
{
    protected array $properties = [];

    public function __construct(
        protected Configuration $configuration
    ) {
    }

    public function getProperties(): array
    {
        return $this->properties;
    }
}
