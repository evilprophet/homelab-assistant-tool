<?php

namespace EvilStudio\HAT\Helper;

use DateTime;
use DateTimeZone;

class Configuration
{
    protected bool $isCronEnabled;
    protected string $sshUsername;
    protected string $sshKeyPath;
    protected string $timezone;

    public function __construct(array $configuration)
    {
        $this->isCronEnabled = (bool)$configuration['cron'];

        $this->sshUsername = $configuration['ssh_username'];
        $this->sshKeyPath = $configuration['ssh_key_path'];

        $this->timezone = $configuration['timezone'];
    }

    public function isCronEnabled(): bool
    {
        return $this->isCronEnabled;
    }

    public function getSshUsername(): string
    {
        return $this->sshUsername;
    }

    public function getSshKey(): string
    {
        return file_get_contents($this->sshKeyPath);
    }

    public function getCurrentDateTime(): DateTime
    {
        return new DateTime('now', new DateTimeZone($this->timezone));
    }
}
