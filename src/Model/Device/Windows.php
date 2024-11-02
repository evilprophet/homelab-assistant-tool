<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Model\Device;

use EvilStudio\HAT\Exception\Platform\NoSupportedAction;
use Symfony\Component\Console\Output\OutputInterface;

class Windows extends Generic
{

    public function stop(): bool
    {
        throw new NoSupportedAction('Stop action is not supported on Windows device.');
    }

    public function ssh(OutputInterface $output): void
    {
        throw new NoSupportedAction('SSH action is not supported on Windows device.');
    }
}
