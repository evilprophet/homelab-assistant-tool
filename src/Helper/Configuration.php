<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Helper;

use DateTime;
use DateTimeZone;
use EvilStudio\HAT\Exception\SshKeyNotReadable;
use Throwable;

class Configuration
{
    protected const string ABSOLUTE_PATH_PATTERN = '/^(\/|[A-Za-z]:[\/\\\\]|\\\\\\\\)/';
    protected const string FALLBACK_TIMEZONE = 'UTC';

    protected bool $isCronEnabled;
    protected bool $isUpsModeEnabled;
    protected string $sshKeyPath;
    protected ?string $sshKeyPassphrase;
    protected string $defaultSshUsername;
    protected string $timezone;
    protected int $actionLogRetentionDays;
    protected string $applicationDirectory;

    public function __construct(array $configuration, string $applicationDirectory = '')
    {
        $this->isCronEnabled = (bool)$configuration['cron'];
        $this->isUpsModeEnabled = (bool)$configuration['ups_mode'];
        $this->sshKeyPath = $configuration['ssh_key_path'];
        $passphrase = trim((string)($configuration['ssh_key_passphrase'] ?? ''));
        $this->sshKeyPassphrase = $passphrase === '' ? null : $passphrase;
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
        $sshKeyPath = $this->getSshKeyPath();
        $sshKey = is_readable($sshKeyPath) ? file_get_contents($sshKeyPath) : false;

        // Returning false against the : string return type would raise a TypeError,
        // which cron does not catch because it extends Error rather than Exception.
        if ($sshKey === false) {
            throw SshKeyNotReadable::forPath($sshKeyPath);
        }

        return $sshKey;
    }

    public function getSshKeyPassphrase(): ?string
    {
        return $this->sshKeyPassphrase;
    }

    public function getSshKeyPath(): string
    {
        return $this->resolveSshKeyPath($this->sshKeyPath);
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

    public function getResolvedTimezone(): DateTimeZone
    {
        try {
            return new DateTimeZone($this->timezone);
        } catch (Throwable) {
            return new DateTimeZone(self::FALLBACK_TIMEZONE);
        }
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
