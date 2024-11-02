<?php

declare(strict_types=1);

namespace EvilStudio\HAT;

use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public function __construct(iterable $commands, string $version)
    {
        parent::__construct('HAT (HomeLab Assistant Tools)', $version);

        foreach ($commands as $command) {
            $this->add($command);
        }
    }
}
