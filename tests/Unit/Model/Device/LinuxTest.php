<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Model\Device;

use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Device\Generic;
use EvilStudio\HAT\Model\Device\Linux;
use PHPUnit\Framework\TestCase;

class LinuxTest extends TestCase
{
    protected Configuration $configuration;

    protected function setUp(): void
    {
        $config = [
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'testuser',
            'timezone' => 'UTC',
        ];

        $this->configuration = new Configuration($config);
    }

    protected function createConfiguredDevice(): Linux
    {
        $device = new Linux($this->configuration);
        $device->configure(
            name: 'Test Linux',
            ip: '192.168.1.100',
            mac: 'AA:BB:CC:DD:EE:FF',
            platform: 'linux',
            upsIdentifier: null,
            upsLowBatteryRuntimeThreshold: null,
            username: 'linuxuser'
        );

        return $device;
    }

    public function testConstructor(): void
    {
        $device = new Linux($this->configuration);

        $this->assertInstanceOf(Linux::class, $device);
        $this->assertInstanceOf(Generic::class, $device);
    }

    public function testConfigureAndGetters(): void
    {
        $device = $this->createConfiguredDevice();

        $this->assertEquals('Test Linux', $device->getName());
        $this->assertEquals('192.168.1.100', $device->getIp());
        $this->assertEquals('AA:BB:CC:DD:EE:FF', $device->getMac());
        $this->assertEquals('linux', $device->getPlatform());
        $this->assertEquals('linuxuser', $device->getUsername());
    }

    public function testInheritsFromGeneric(): void
    {
        $device = $this->createConfiguredDevice();

        $this->assertInstanceOf(Generic::class, $device);
    }
}
