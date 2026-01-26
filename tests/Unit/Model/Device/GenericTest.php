<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Model\Device;

use EvilStudio\HAT\Exception\Platform\NoSupportedAction;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Device\Generic;
use EvilStudio\HAT\Service\NetworkService;
use PHPUnit\Framework\TestCase;

class GenericTest extends TestCase
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

    protected function createConfiguredDevice(): Generic
    {
        $device = new Generic($this->configuration);
        $device->configure(
            name: 'Test Device',
            ip: '192.168.1.50',
            mac: '00:11:22:33:44:55',
            platform: 'generic',
            upsIdentifier: 'ups1',
            upsLowBatteryRuntimeThreshold: 600,
            username: null
        );

        return $device;
    }

    public function testConstructor(): void
    {
        $device = new Generic($this->configuration);

        $this->assertInstanceOf(Generic::class, $device);
    }

    public function testConfigureAndGetters(): void
    {
        $device = $this->createConfiguredDevice();

        $this->assertEquals('Test Device', $device->getName());
        $this->assertEquals('192.168.1.50', $device->getIp());
        $this->assertEquals('00:11:22:33:44:55', $device->getMac());
        $this->assertEquals('generic', $device->getPlatform());
        $this->assertEquals('ups1', $device->getUpsIdentifier());
        $this->assertEquals(600, $device->getUpsLowBatteryRuntimeThreshold());
        $this->assertEquals('testuser', $device->getUsername());
        $this->assertNull($device->getStatus());
    }

    public function testConfigureWithCustomUsername(): void
    {
        $device = new Generic($this->configuration);
        $device->configure(
            name: 'Test Device',
            ip: '192.168.1.50',
            mac: '00:11:22:33:44:55',
            platform: 'generic',
            upsIdentifier: null,
            upsLowBatteryRuntimeThreshold: null,
            username: 'customuser'
        );

        $this->assertEquals('customuser', $device->getUsername());
    }

    public function testConfigureWithNullUsername(): void
    {
        $device = new Generic($this->configuration);
        $device->configure(
            name: 'Test Device',
            ip: '192.168.1.50',
            mac: '00:11:22:33:44:55',
            platform: 'generic',
            upsIdentifier: null,
            upsLowBatteryRuntimeThreshold: null,
            username: null
        );

        $this->assertEquals('testuser', $device->getUsername());
    }

    public function testConfigureWithNullUpsIdentifier(): void
    {
        $device = new Generic($this->configuration);
        $device->configure(
            name: 'Test Device',
            ip: '192.168.1.50',
            mac: '00:11:22:33:44:55',
            platform: 'generic',
            upsIdentifier: null,
            upsLowBatteryRuntimeThreshold: null,
            username: null
        );

        $this->assertNull($device->getUpsIdentifier());
    }

    public function testConfigureWithNullThreshold(): void
    {
        $device = new Generic($this->configuration);
        $device->configure(
            name: 'Test Device',
            ip: '192.168.1.50',
            mac: '00:11:22:33:44:55',
            platform: 'generic',
            upsIdentifier: 'ups1',
            upsLowBatteryRuntimeThreshold: null,
            username: null
        );

        $this->assertEquals(0, $device->getUpsLowBatteryRuntimeThreshold());
    }

    public function testToArrayWithoutStatus(): void
    {
        $device = $this->createConfiguredDevice();
        $result = $device->toArray();

        $this->assertEquals('Test Device', $result['name']);
        $this->assertEquals('192.168.1.50', $result['ip']);
        $this->assertEquals('00:11:22:33:44:55', $result['mac']);
        $this->assertEquals('generic', $result['platform']);
        $this->assertEquals('ups1', $result['ups']);
        $this->assertEquals('10 min', $result['ups_low_battery_runtime_threshold']);
        $this->assertArrayNotHasKey('status', $result);
    }

    public function testToArrayWithNullUpsIdentifier(): void
    {
        $device = new Generic($this->configuration);
        $device->configure(
            name: 'Test Device',
            ip: '192.168.1.50',
            mac: '00:11:22:33:44:55',
            platform: 'generic',
            upsIdentifier: null,
            upsLowBatteryRuntimeThreshold: null,
            username: null
        );

        $result = $device->toArray();

        $this->assertEquals('-', $result['ups']);
        $this->assertEquals('-', $result['ups_low_battery_runtime_threshold']);
    }

    public function testStopThrowsNoSupportedActionException(): void
    {
        $device = $this->createConfiguredDevice();

        $this->expectException(NoSupportedAction::class);
        $this->expectExceptionMessage("Stop action is not supported on 'generic' device.");

        $device->stop();
    }

    public function testStopExceptionMessageContainsPlatform(): void
    {
        $device = new Generic($this->configuration);
        $device->configure(
            name: 'Test Device',
            ip: '192.168.1.50',
            mac: '00:11:22:33:44:55',
            platform: 'windows',
            upsIdentifier: null,
            upsLowBatteryRuntimeThreshold: null,
            username: null
        );

        $this->expectException(NoSupportedAction::class);
        $this->expectExceptionMessage("Stop action is not supported on 'windows' device.");

        $device->stop();
    }

    public function testConfigureReturnsDeviceInterface(): void
    {
        $device = new Generic($this->configuration);
        $result = $device->configure(
            name: 'Test Device',
            ip: '192.168.1.50',
            mac: '00:11:22:33:44:55',
            platform: 'generic',
            upsIdentifier: null,
            upsLowBatteryRuntimeThreshold: null,
            username: null
        );

        $this->assertSame($device, $result);
    }

    public function testCheckStatusUsesNetworkService(): void
    {
        $networkServiceMock = $this->createMock(NetworkService::class);
        $networkServiceMock->expects($this->once())
            ->method('ping')
            ->with('192.168.1.10')
            ->willReturn(true);

        $device = new Generic($this->configuration, $networkServiceMock);
        $device->configure(
            name: 'Test Device',
            ip: '192.168.1.10',
            mac: '00:11:22:33:44:55',
            platform: 'generic',
            upsIdentifier: null,
            upsLowBatteryRuntimeThreshold: null,
            username: null
        );

        $device->checkStatus();

        $this->assertTrue($device->getStatus());
    }

    public function testStartUsesNetworkService(): void
    {
        $networkServiceMock = $this->createMock(NetworkService::class);
        $networkServiceMock->expects($this->once())
            ->method('wakeOnLan')
            ->with('AA:BB:CC:DD:EE:FF')
            ->willReturn(true);

        $device = new Generic($this->configuration, $networkServiceMock);
        $device->configure(
            name: 'Test Device',
            ip: '192.168.1.20',
            mac: 'AA:BB:CC:DD:EE:FF',
            platform: 'generic',
            upsIdentifier: null,
            upsLowBatteryRuntimeThreshold: null,
            username: null
        );

        $result = $device->start();

        $this->assertTrue($result);
    }
}
