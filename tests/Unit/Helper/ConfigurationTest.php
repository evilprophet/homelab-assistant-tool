<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Helper;

use EvilStudio\HAT\Exception\SshKeyNotReadable;
use EvilStudio\HAT\Helper\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    use \EvilStudio\HAT\Tests\Support\TemporaryPathTrait;

    protected function tearDown(): void
    {
        $this->removeTemporaryPaths();

        parent::tearDown();
    }
    public function testReturnsConfiguredValuesAndCurrentDateTimeTimezone(): void
    {
        $configuration = new Configuration([
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => '/tmp/test-key',
            'default_ssh_username' => 'admin',
            'timezone' => 'Europe/Warsaw',
        ]);

        $this->assertTrue($configuration->isCronEnabled());
        $this->assertFalse($configuration->isUpsModeEnabled());
        $this->assertSame('admin', $configuration->getDefaultSshUsername());
        $this->assertSame('Europe/Warsaw', $configuration->getTimezone());
        $this->assertSame('Europe/Warsaw', $configuration->getCurrentDateTime()->getTimezone()->getName());
        $this->assertSame(90, $configuration->getActionLogRetentionDays());
    }

    public function testResolvedTimezoneReturnsConfiguredZone(): void
    {
        $configuration = $this->createConfigurationWithTimezone('Europe/Warsaw');

        $this->assertSame('Europe/Warsaw', $configuration->getResolvedTimezone()->getName());
    }

    public function testResolvedTimezoneFallsBackToUtcForInvalidZone(): void
    {
        $configuration = $this->createConfigurationWithTimezone('Europe/Warsawa');

        $this->assertSame('UTC', $configuration->getResolvedTimezone()->getName());
    }

    public function testResolvedTimezoneFallsBackToUtcForEmptyZone(): void
    {
        $configuration = $this->createConfigurationWithTimezone('');

        $this->assertSame('UTC', $configuration->getResolvedTimezone()->getName());
    }

    public function testReadsSshKeyFromConfiguredPath(): void
    {
        $keyPath = $this->createTemporaryPath('hat-test-key-');
        file_put_contents($keyPath, 'ssh-private-key-content');

        $configuration = new Configuration([
            'cron' => true,
            'ups_mode' => true,
            'ssh_key_path' => $keyPath,
            'default_ssh_username' => 'root',
            'timezone' => 'UTC',
        ]);

        $this->assertSame('ssh-private-key-content', $configuration->getSshKey());
    }

    public function testGetSshKeyThrowsDescriptiveExceptionWhenTheKeyIsMissing(): void
    {
        $missingKeyPath = $this->createTemporaryPath('hat-missing-key-');

        $configuration = new Configuration([
            'cron' => true,
            'ups_mode' => true,
            'ssh_key_path' => $missingKeyPath,
            'default_ssh_username' => 'root',
            'timezone' => 'UTC',
        ]);

        $this->expectException(SshKeyNotReadable::class);
        $this->expectExceptionMessage(sprintf("SSH key '%s' is missing or not readable.", $missingKeyPath));

        $configuration->getSshKey();
    }

    public function testReadsSshKeyFromRelativePathUsingApplicationDirectory(): void
    {
        $baseDir = $this->createTemporaryPath('hat-test-app-');
        $relativeKeyPath = 'var/data/id_ed25519';
        $fullKeyPath = $baseDir . '/' . $relativeKeyPath;
        if (!is_dir(dirname($fullKeyPath))) {
            mkdir(dirname($fullKeyPath), 0777, true);
        }
        file_put_contents($fullKeyPath, 'ssh-private-key-relative-content');

        $configuration = new Configuration([
            'cron' => true,
            'ups_mode' => true,
            'ssh_key_path' => './' . $relativeKeyPath,
            'default_ssh_username' => 'root',
            'timezone' => 'UTC',
        ], $baseDir);

        $this->assertSame('ssh-private-key-relative-content', $configuration->getSshKey());
    }

    protected function createConfigurationWithTimezone(string $timezone): Configuration
    {
        return new Configuration([
            'cron' => true,
            'ups_mode' => true,
            'ssh_key_path' => '/tmp/test-key',
            'default_ssh_username' => 'root',
            'timezone' => $timezone,
        ]);
    }
}
