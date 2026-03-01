<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Runtime\Device;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Linux extends Generic
{
    public function stop(): bool
    {
        return $this->executeSshCommand('systemctl poweroff');
    }

    public function ssh(OutputInterface $output): void
    {
        $sshCommand = sprintf('%s@%s', $this->getUsername(), $this->getIp());

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

    protected function executeSshCommand(string $command): bool
    {
        $ssh = $this->createSshClient($this->getIp());
        $sshKey = $this->loadSshKey();

        if (!$ssh->login($this->getUsername(), $sshKey)) {
            return false;
        }

        $output = (string)$ssh->exec($command);
        $exitStatus = $ssh->getExitStatus();
        if ($exitStatus !== null) {
            return $exitStatus === 0;
        }

        return trim($output) === '';
    }

    protected function createSshClient(string $ip): SSH2
    {
        return new SSH2($ip);
    }

    protected function loadSshKey(): mixed
    {
        return PublicKeyLoader::load($this->configuration->getSshKey());
    }
}
