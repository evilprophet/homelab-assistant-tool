<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Provider;

use EvilStudio\HAT\Exception\MissingDevice;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Device\Generic;
use EvilStudio\HAT\Model\Device\Linux;
use EvilStudio\HAT\Model\DeviceFactory;
use EvilStudio\HAT\Provider\DeviceProvider;
use PHPUnit\Framework\TestCase;

class DeviceProviderTest extends TestCase
{
    protected Configuration $configuration;
    protected DeviceFactory $deviceFactory;

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
        $this->deviceFactory = new DeviceFactory($this->configuration);
    }

    public function testConstructorWithEmptyDevicesData(): void
    {
        $provider = new DeviceProvider($this->configuration, $this->deviceFactory, []);

        $this->assertInstanceOf(DeviceProvider::class, $provider);
        $this->assertEmpty($provider->getDeviceList());
    }

    public function testConstructorWithDevicesData(): void
    {
        $devicesData = [
            [
                'name' => 'Device 1',
                'ip' => '192.168.1.10',
                'mac' => '00:11:22:33:44:55',
                'platform' => 'linux',
            ],
            [
                'name' => 'Device 2',
                'ip' => '192.168.1.20',
                'mac' => 'AA:BB:CC:DD:EE:FF',
                'platform' => 'generic',
            ],
        ];

        $provider = new DeviceProvider($this->configuration, $this->deviceFactory, $devicesData);
        $deviceList = $provider->getDeviceList();

        $this->assertCount(2, $deviceList);
        $this->assertArrayHasKey('Device 1', $deviceList);
        $this->assertArrayHasKey('Device 2', $deviceList);
    }

    public function testGetDeviceListReturnsCorrectData(): void
    {
        $devicesData = [
            [
                'name' => 'Test Device',
                'ip' => '192.168.1.100',
                'mac' => '11:22:33:44:55:66',
                'platform' => 'linux',
                'ups_identifier' => 'ups1',
                'ups_low_battery_runtime_threshold' => 600,
                'username' => 'admin',
            ],
        ];

        $provider = new DeviceProvider($this->configuration, $this->deviceFactory, $devicesData);
        $deviceList = $provider->getDeviceList();

        $this->assertCount(1, $deviceList);

        $device = $deviceList['Test Device'];
        $this->assertInstanceOf(Linux::class, $device);
        $this->assertEquals('Test Device', $device->getName());
        $this->assertEquals('192.168.1.100', $device->getIp());
        $this->assertEquals('11:22:33:44:55:66', $device->getMac());
        $this->assertEquals('linux', $device->getPlatform());
        $this->assertEquals('ups1', $device->getUpsIdentifier());
        $this->assertEquals(600, $device->getUpsLowBatteryRuntimeThreshold());
        $this->assertEquals('admin', $device->getUsername());
    }

    public function testGetDeviceReturnsCorrectDevice(): void
    {
        $devicesData = [
            [
                'name' => 'Main Server',
                'ip' => '192.168.1.50',
                'mac' => 'FF:EE:DD:CC:BB:AA',
                'platform' => 'generic',
            ],
        ];

        $provider = new DeviceProvider($this->configuration, $this->deviceFactory, $devicesData);
        $device = $provider->getDevice('Main Server');

        $this->assertInstanceOf(Generic::class, $device);
        $this->assertEquals('Main Server', $device->getName());
    }

    public function testGetDeviceThrowsExceptionWhenNotFound(): void
    {
        $provider = new DeviceProvider($this->configuration, $this->deviceFactory, []);

        $this->expectException(MissingDevice::class);
        $this->expectExceptionMessage("Device with name 'nonexistent' not found.");

        $provider->getDevice('nonexistent');
    }

    public function testGetDeviceThrowsExceptionWithCorrectMessage(): void
    {
        $devicesData = [
            ['name' => 'Device 1', 'ip' => '192.168.1.1', 'mac' => '00:00:00:00:00:01', 'platform' => 'linux'],
        ];

        $provider = new DeviceProvider($this->configuration, $this->deviceFactory, $devicesData);

        $this->expectException(MissingDevice::class);
        $this->expectExceptionMessage("Device with name 'Wrong Device' not found.");

        $provider->getDevice('Wrong Device');
    }

    public function testGetProperties(): void
    {
        $provider = new DeviceProvider($this->configuration, $this->deviceFactory, []);
        $properties = $provider->getProperties();

        $this->assertEquals(
            ['Name', 'IP', 'MAC', 'Platform', 'UPS', 'UPS Low Battery Runtime Threshold'],
            $properties
        );
    }

    public function testConstructorWithOptionalFields(): void
    {
        $devicesData = [
            [
                'name' => 'Minimal Device',
                'ip' => '192.168.1.1',
                'mac' => '00:00:00:00:00:01',
                'platform' => 'generic',
            ],
        ];

        $provider = new DeviceProvider($this->configuration, $this->deviceFactory, $devicesData);
        $device = $provider->getDevice('Minimal Device');

        $this->assertNull($device->getUpsIdentifier());
        $this->assertEquals(0, $device->getUpsLowBatteryRuntimeThreshold());
        $this->assertEquals('testuser', $device->getUsername());
    }

    public function testDeviceListIsIndexedByName(): void
    {
        $devicesData = [
            ['name' => 'First', 'ip' => '192.168.1.1', 'mac' => '00:00:00:00:00:01', 'platform' => 'linux'],
            ['name' => 'Second', 'ip' => '192.168.1.2', 'mac' => '00:00:00:00:00:02', 'platform' => 'generic'],
            ['name' => 'Third', 'ip' => '192.168.1.3', 'mac' => '00:00:00:00:00:03', 'platform' => 'linux'],
        ];

        $provider = new DeviceProvider($this->configuration, $this->deviceFactory, $devicesData);
        $deviceList = $provider->getDeviceList();

        $this->assertArrayHasKey('First', $deviceList);
        $this->assertArrayHasKey('Second', $deviceList);
        $this->assertArrayHasKey('Third', $deviceList);

        $this->assertEquals('First', $deviceList['First']->getName());
        $this->assertEquals('Second', $deviceList['Second']->getName());
        $this->assertEquals('Third', $deviceList['Third']->getName());
    }

    public function testDeviceFactoryCreatesCorrectDeviceTypes(): void
    {
        $devicesData = [
            ['name' => 'Linux Server', 'ip' => '192.168.1.10', 'mac' => '00:00:00:00:00:10', 'platform' => 'linux'],
            ['name' => 'Generic Device', 'ip' => '192.168.1.20', 'mac' => '00:00:00:00:00:20', 'platform' => 'generic'],
        ];

        $provider = new DeviceProvider($this->configuration, $this->deviceFactory, $devicesData);

        $this->assertInstanceOf(Linux::class, $provider->getDevice('Linux Server'));
        $this->assertInstanceOf(Generic::class, $provider->getDevice('Generic Device'));
    }
}
