<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Helper;

use DateTime;
use DateTimeZone;

class Configuration
{
    protected const string ABSOLUTE_PATH_PATTERN = '/^(\/|[A-Za-z]:[\/\\\\]|\\\\\\\\)/';

    protected bool $isCronEnabled;
    protected bool $isUpsModeEnabled;
    protected string $sshKeyPath;
    protected string $defaultSshUsername;
    protected string $timezone;
    protected int $actionLogRetentionDays;
    protected string $applicationDirectory;

    public function __construct(array $configuration, string $applicationDirectory = '')
    {
        $this->isCronEnabled = (bool)$configuration['cron'];
        $this->isUpsModeEnabled = (bool)$configuration['ups_mode'];
        $this->sshKeyPath = $configuration['ssh_key_path'];
        $this->defaultSshUsername = $configuration['default_ssh_username'];
        $this->timezone = $configuration['timezone'];
        $this->actionLogRetentionDays = max(1, (int)($configuration['action_log_retention_days'] ?? 90));
        $this->applicationDirectory = rtrim($applicationDirectory, '/\\');
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
        $sshKeyPath = $this->resolveSshKeyPath($this->sshKeyPath);

        return file_get_contents($sshKeyPath);
    }

    public function getDefaultSshUsername(): string
    {
        return $this->defaultSshUsername;
    }

    public function getCurrentDateTime(): DateTime
    {
        return new DateTime('now', new DateTimeZone($this->timezone));
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function getActionLogRetentionDays(): int
    {
        return $this->actionLogRetentionDays;
    }

    protected function resolveSshKeyPath(string $sshKeyPath): string
    {
        if ($sshKeyPath === '' || $this->isAbsolutePath($sshKeyPath)) {
            return $sshKeyPath;
        }

        if ($this->applicationDirectory === '') {
            return $sshKeyPath;
        }

        return $this->applicationDirectory . '/' . ltrim($sshKeyPath, '/\\');
    }

    protected function isAbsolutePath(string $path): bool
    {
        return preg_match(self::ABSOLUTE_PATH_PATTERN, $path) === 1;
    }
}
