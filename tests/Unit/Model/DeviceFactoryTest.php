<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Model;

use EvilStudio\HAT\Api\DeviceInterface;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Device\Generic;
use EvilStudio\HAT\Model\Device\Linux;
use EvilStudio\HAT\Model\Device\ProxmoxVE;
use EvilStudio\HAT\Model\DeviceFactory;
use PHPUnit\Framework\TestCase;

class DeviceFactoryTest extends TestCase
{
    protected Configuration $configuration;
    protected DeviceFactory $factory;

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
        $this->factory = new DeviceFactory($this->configuration);
    }

    public function testCreateLinuxDeviceForLinuxPlatform(): void
    {
        $device = $this->factory->createDevice('linux');

        $this->assertInstanceOf(Linux::class, $device);
        $this->assertInstanceOf(DeviceInterface::class, $device);
    }

    public function testCreateLinuxDeviceForDebianPlatform(): void
    {
        $device = $this->factory->createDevice('debian');

        $this->assertInstanceOf(Linux::class, $device);
        $this->assertInstanceOf(DeviceInterface::class, $device);
    }

    public function testCreateLinuxDeviceForUbuntuPlatform(): void
    {
        $device = $this->factory->createDevice('ubuntu');

        $this->assertInstanceOf(Linux::class, $device);
        $this->assertInstanceOf(DeviceInterface::class, $device);
    }

    public function testCreateLinuxDeviceForProxmoxDmPlatform(): void
    {
        $device = $this->factory->createDevice('proxmox_dm');

        $this->assertInstanceOf(Linux::class, $device);
        $this->assertInstanceOf(DeviceInterface::class, $device);
    }

    public function testCreateLinuxDeviceForProxmoxBsPlatform(): void
    {
        $device = $this->factory->createDevice('proxmox_bs');

        $this->assertInstanceOf(Linux::class, $device);
        $this->assertInstanceOf(DeviceInterface::class, $device);
    }

    public function testCreateProxmoxVEDeviceForProxmoxVePlatform(): void
    {
        $device = $this->factory->createDevice('proxmox_ve');

        $this->assertInstanceOf(ProxmoxVE::class, $device);
        $this->assertInstanceOf(Linux::class, $device);
        $this->assertInstanceOf(DeviceInterface::class, $device);
    }

    public function testCreateGenericDeviceForUnknownPlatform(): void
    {
        $device = $this->factory->createDevice('unknown_platform');

        $this->assertInstanceOf(Generic::class, $device);
        $this->assertInstanceOf(DeviceInterface::class, $device);
    }

    public function testCreateGenericDeviceForWindowsPlatform(): void
    {
        $device = $this->factory->createDevice('windows');

        $this->assertInstanceOf(Generic::class, $device);
        $this->assertInstanceOf(DeviceInterface::class, $device);
    }

    public function testCreateGenericDeviceForEmptyString(): void
    {
        $device = $this->factory->createDevice('');

        $this->assertInstanceOf(Generic::class, $device);
        $this->assertInstanceOf(DeviceInterface::class, $device);
    }

    public function testAllDevicesImplementDeviceInterface(): void
    {
        $platforms = ['linux', 'debian', 'ubuntu', 'proxmox_dm', 'proxmox_bs', 'proxmox_ve', 'unknown'];

        foreach ($platforms as $platform) {
            $device = $this->factory->createDevice($platform);
            $this->assertInstanceOf(DeviceInterface::class, $device, "Platform '{$platform}' should implement DeviceInterface");
        }
    }

    public function testFactoryUsesConfiguration(): void
    {
        $device = $this->factory->createDevice('linux');

        $this->assertInstanceOf(Linux::class, $device);
    }
}
