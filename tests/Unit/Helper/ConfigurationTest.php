<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Helper;

use DateTime;
use DateTimeZone;
use EvilStudio\HAT\Helper\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $config = [
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'testuser',
            'timezone' => 'Europe/Warsaw',
        ];

        $configuration = new Configuration($config);

        $this->assertTrue($configuration->isCronEnabled());
        $this->assertFalse($configuration->isUpsModeEnabled());
        $this->assertEquals('testuser', $configuration->getDefaultSshUsername());
    }

    public function testIsCronEnabledWhenDisabled(): void
    {
        $config = [
            'cron' => false,
            'ups_mode' => true,
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'admin',
            'timezone' => 'UTC',
        ];

        $configuration = new Configuration($config);

        $this->assertFalse($configuration->isCronEnabled());
        $this->assertTrue($configuration->isUpsModeEnabled());
    }

    public function testGetCurrentDateTimeWithDifferentTimezones(): void
    {
        $config = [
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'user',
            'timezone' => 'America/New_York',
        ];

        $configuration = new Configuration($config);
        $dateTime = $configuration->getCurrentDateTime();

        $this->assertInstanceOf(DateTime::class, $dateTime);
        $this->assertEquals('America/New_York', $dateTime->getTimezone()->getName());
    }

    public function testGetCurrentDateTimeWithUTC(): void
    {
        $config = [
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'user',
            'timezone' => 'UTC',
        ];

        $configuration = new Configuration($config);
        $dateTime = $configuration->getCurrentDateTime();

        $this->assertEquals('UTC', $dateTime->getTimezone()->getName());
    }

    public function testGetCurrentDateTimeWithEuropeWarsaw(): void
    {
        $config = [
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'user',
            'timezone' => 'Europe/Warsaw',
        ];

        $configuration = new Configuration($config);
        $dateTime = $configuration->getCurrentDateTime();

        $this->assertEquals('Europe/Warsaw', $dateTime->getTimezone()->getName());
        $this->assertInstanceOf(DateTime::class, $dateTime);
    }

    public function testBooleanTypeCoercion(): void
    {
        $config = [
            'cron' => '1',
            'ups_mode' => '0',
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'user',
            'timezone' => 'UTC',
        ];

        $configuration = new Configuration($config);

        $this->assertTrue($configuration->isCronEnabled());
        $this->assertFalse($configuration->isUpsModeEnabled());
    }

    public function testWithEmptyStrings(): void
    {
        $config = [
            'cron' => '',
            'ups_mode' => '',
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => '',
            'timezone' => 'UTC',
        ];

        $configuration = new Configuration($config);

        $this->assertFalse($configuration->isCronEnabled());
        $this->assertFalse($configuration->isUpsModeEnabled());
        $this->assertEquals('', $configuration->getDefaultSshUsername());
    }

    public function testGetSshKey(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'ssh_key_test_');
        $keyContent = "-----BEGIN RSA PRIVATE KEY-----\ntest key content\n-----END RSA PRIVATE KEY-----";
        file_put_contents($tempFile, $keyContent);

        $config = [
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => $tempFile,
            'default_ssh_username' => 'testuser',
            'timezone' => 'UTC',
        ];

        $configuration = new Configuration($config);

        $this->assertEquals($keyContent, $configuration->getSshKey());

        unlink($tempFile);
    }
}
