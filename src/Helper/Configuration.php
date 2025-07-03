<?php

namespace EvilStudio\HAT\Helper;

use DateTime;
use DateTimeZone;

class Configuration
{
    protected bool $isCronEnabled;
    protected bool $isUpsModeEnabled;
    protected string $sshKeyPath;
    protected string $defaultSshUsername;
    protected string $timezone;

    public function __construct(array $configuration)
    {
        $this->isCronEnabled = (bool)$configuration['cron'];
        $this->isUpsModeEnabled = (bool)$configuration['ups_mode'];
        $this->sshKeyPath = $configuration['ssh_key_path'];
        $this->defaultSshUsername = $configuration['default_ssh_username'];
        $this->timezone = $configuration['timezone'];
    }

    public function isCronEnabled(): bool
    {
        return $this->isCronEnabled;
    }

    public function isUpsModeEnabled(): bool
    {
        return $this->isUpsModeEnabled;
    }

    public function getSshKey(): string
    {
        return file_get_contents($this->sshKeyPath);
    }

    public function getDefaultSshUsername(): string
    {
        return $this->defaultSshUsername;
    }

    public function getCurrentDateTime(): DateTime
    {
        return new DateTime('now', new DateTimeZone($this->timezone));
    }
}
