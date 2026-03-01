<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Helper;

use EvilStudio\HAT\Helper\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
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
    }

    public function testReadsSshKeyFromConfiguredPath(): void
    {
        $keyPath = sys_get_temp_dir() . '/hat-test-key-' . uniqid('', true);
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

    public function testReadsSshKeyFromRelativePathUsingApplicationDirectory(): void
    {
        $baseDir = sys_get_temp_dir() . '/hat-test-app-' . uniqid('', true);
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
}
