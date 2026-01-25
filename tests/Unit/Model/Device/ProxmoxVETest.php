<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Model\Device;

use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Device\Generic;
use EvilStudio\HAT\Model\Device\Linux;
use EvilStudio\HAT\Model\Device\ProxmoxVE;
use PHPUnit\Framework\TestCase;

class ProxmoxVETest extends TestCase
{
    protected Configuration $configuration;

    protected function setUp(): void
    {
        $config = [
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'root',
            'timezone' => 'UTC',
        ];

        $this->configuration = new Configuration($config);
    }

    protected function createConfiguredDevice(): ProxmoxVE
    {
        $device = new ProxmoxVE($this->configuration);
        $device->configure(
            name: 'Proxmox Server',
            ip: '192.168.1.200',
            mac: 'FF:EE:DD:CC:BB:AA',
            platform: 'proxmox_ve',
            upsIdentifier: 'ups1',
            upsLowBatteryRuntimeThreshold: 300,
            username: 'root'
        );

        return $device;
    }

    public function testConstructor(): void
    {
        $device = new ProxmoxVE($this->configuration);

        $this->assertInstanceOf(ProxmoxVE::class, $device);
        $this->assertInstanceOf(Linux::class, $device);
        $this->assertInstanceOf(Generic::class, $device);
    }

    public function testConfigureAndGetters(): void
    {
        $device = $this->createConfiguredDevice();

        $this->assertEquals('Proxmox Server', $device->getName());
        $this->assertEquals('192.168.1.200', $device->getIp());
        $this->assertEquals('FF:EE:DD:CC:BB:AA', $device->getMac());
        $this->assertEquals('proxmox_ve', $device->getPlatform());
        $this->assertEquals('root', $device->getUsername());
        $this->assertEquals('ups1', $device->getUpsIdentifier());
        $this->assertEquals(300, $device->getUpsLowBatteryRuntimeThreshold());
    }

    public function testInheritsFromLinux(): void
    {
        $device = $this->createConfiguredDevice();

        $this->assertInstanceOf(Linux::class, $device);
    }

    public function testInheritsFromGeneric(): void
    {
        $device = $this->createConfiguredDevice();

        $this->assertInstanceOf(Generic::class, $device);
    }

    public function testInheritsAllMethodsFromLinux(): void
    {
        $device = $this->createConfiguredDevice();

        $this->assertTrue(method_exists($device, 'start'));
        $this->assertTrue(method_exists($device, 'stop'));
        $this->assertTrue(method_exists($device, 'ssh'));
        $this->assertTrue(method_exists($device, 'checkStatus'));
    }
}
