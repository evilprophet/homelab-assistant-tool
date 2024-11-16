<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Model\Device;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Linux extends Generic
{
    public function stop(): bool
    {
        $ssh = new SSH2($this->getIp());
        $sshKey = PublicKeyLoader::load($this->configuration->getSshKey());

        if (!$ssh->login($this->getUsername(), $sshKey)) {
            return false;
        }

        if ($ssh->exec('systemctl poweroff') != ''){
            return false;
        }

        return true;
    }

    public function ssh(OutputInterface $output): void
    {
        $user = $this->configuration->getSshUsername();
        $sshCommand = sprintf('%s@%s', $user, $this->getIp());

        $process = new Process(['ssh', '-t', $sshCommand]);
        $process->setTty(true);
        $process->setTimeout(null);

        $callback = function ($type, $buffer) use ($output) {
            if (!empty(trim($buffer))) {
                $output->write($buffer, false, OutputInterface::OUTPUT_RAW);
            }
        };

        $process->run($callback);
    }
}
