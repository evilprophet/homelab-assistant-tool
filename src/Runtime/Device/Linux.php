<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Runtime\Device;

use EvilStudio\HAT\Exception\SshCommandFailed;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Linux extends Generic
{
    protected const int SSH_COMMAND_TIMEOUT_SECONDS = 30;
    protected const string POWEROFF_COMMAND = 'systemctl poweroff';

    public function stop(): bool
    {
        return $this->executeSshCommand(self::POWEROFF_COMMAND);
    }

    public function ssh(OutputInterface $output): int
    {
        $process = new Process($this->buildSshArguments());
        $process->setTty(true);
        $process->setTimeout(null);

        return $process->run();
    }

    protected function buildSshArguments(): array
    {
        $arguments = ['ssh', '-t'];

        // Without this the interactive session ignores the key configured through
        // hat:setup:configure, which the non-interactive stop path does use.
        $sshKeyPath = $this->configuration->getSshKeyPath();
        if ($sshKeyPath !== '' && is_readable($sshKeyPath)) {
            $arguments[] = '-i';
            $arguments[] = $sshKeyPath;
        }

        // '-l user' plus '--' keeps the login and the host out of option parsing,
        // unlike a single 'user@host' token.
        $arguments[] = '-l';
        $arguments[] = (string)$this->getUsername();
        $arguments[] = '--';
        $arguments[] = $this->getIp();

        return $arguments;
    }

    protected function executeSshCommand(string $command): bool
    {
        $ssh = $this->createSshClient($this->getIp());
        $sshKey = $this->loadSshKey();

        if (!$ssh->login($this->getUsername(), $sshKey)) {
            throw new SshCommandFailed(
                sprintf(
                    "SSH login as '%s' to device '%s' failed. Check the configured key and the remote authorized_keys.",
                    $this->getUsername(),
                    $this->getName()
                )
            );
        }

        $output = (string)$ssh->exec($command);
        $exitStatus = $ssh->getExitStatus();
        if ($exitStatus === false) {
            return trim($output) === '';
        }

        if ($exitStatus === 0) {
            return true;
        }

        // Without this the caller only sees `false`, which hides the usual cause:
        // a non-root SSH user with no polkit rule for `systemctl poweroff`.
        throw new SshCommandFailed(
            sprintf(
                "Command '%s' on device '%s' exited with status %d: %s",
                $command,
                $this->getName(),
                $exitStatus,
                trim($output) === '' ? '(no output)' : trim($output)
            )
        );
    }

    protected function createSshClient(string $ip): SSH2
    {
        $ssh = new SSH2($ip);
        // The constructor timeout covers connecting only; without this a host that
        // accepts the connection and then hangs blocks the caller indefinitely.
        $ssh->setTimeout(self::SSH_COMMAND_TIMEOUT_SECONDS);

        return $ssh;
    }

    protected function loadSshKey(): mixed
    {
        $passphrase = $this->configuration->getSshKeyPassphrase();

        return $passphrase === null
            ? PublicKeyLoader::load($this->configuration->getSshKey())
            : PublicKeyLoader::load($this->configuration->getSshKey(), $passphrase);
    }
}
