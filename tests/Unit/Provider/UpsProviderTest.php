<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Provider;

use EvilStudio\HAT\Exception\MissingUps;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Api\UpsInterface;
use EvilStudio\HAT\Model\Ups;
use EvilStudio\HAT\Provider\UpsProvider;
use PHPUnit\Framework\TestCase;

class UpsProviderTest extends TestCase
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

    public function testConstructorWithEmptyUpsData(): void
    {
        $provider = new UpsProvider($this->configuration, []);

        $this->assertInstanceOf(UpsProvider::class, $provider);
        $this->assertEmpty($provider->getUpsList());
    }

    public function testConstructorWithUpsData(): void
    {
        $upsData = [
            [
                'name' => 'UPS 1',
                'identifier' => 'ups1',
                'host' => '192.168.1.100',
                'safe_battery_runtime_threshold' => 600,
            ],
            [
                'name' => 'UPS 2',
                'identifier' => 'ups2',
                'host' => '192.168.1.101',
                'safe_battery_runtime_threshold' => 300,
            ],
        ];

        $provider = new UpsProvider($this->configuration, $upsData);
        $upsList = $provider->getUpsList();

        $this->assertCount(2, $upsList);
        $this->assertArrayHasKey('ups1', $upsList);
        $this->assertArrayHasKey('ups2', $upsList);
        $this->assertInstanceOf(Ups::class, $upsList['ups1']);
        $this->assertInstanceOf(Ups::class, $upsList['ups2']);
    }

    public function testGetUpsListReturnsCorrectData(): void
    {
        $upsData = [
            [
                'name' => 'Test UPS',
                'identifier' => 'test_ups',
                'host' => '192.168.1.200',
                'safe_battery_runtime_threshold' => 450,
            ],
        ];

        $provider = new UpsProvider($this->configuration, $upsData);
        $upsList = $provider->getUpsList();

        $this->assertCount(1, $upsList);

        $ups = $upsList['test_ups'];
        $this->assertEquals('Test UPS', $ups->getName());
        $this->assertEquals('test_ups', $ups->getIdentifier());
        $this->assertEquals('192.168.1.200', $ups->getHost());
        $this->assertEquals(450, $ups->getSafeBatteryRuntimeThreshold());
    }

    public function testGetUpsReturnsCorrectUps(): void
    {
        $upsData = [
            [
                'name' => 'UPS Main',
                'identifier' => 'ups_main',
                'host' => '192.168.1.100',
                'safe_battery_runtime_threshold' => 600,
            ],
        ];

        $provider = new UpsProvider($this->configuration, $upsData);
        $ups = $provider->getUps('ups_main');

        $this->assertInstanceOf(Ups::class, $ups);
        $this->assertEquals('UPS Main', $ups->getName());
        $this->assertEquals('ups_main', $ups->getIdentifier());
    }

    public function testGetUpsThrowsExceptionWhenNotFound(): void
    {
        $provider = new UpsProvider($this->configuration, []);

        $this->expectException(MissingUps::class);
        $this->expectExceptionMessage("UPS with ups_name 'nonexistent' not found.");

        $provider->getUps('nonexistent');
    }

    public function testGetUpsThrowsExceptionWithCorrectMessage(): void
    {
        $upsData = [
            ['name' => 'UPS 1', 'identifier' => 'ups1', 'host' => '192.168.1.100'],
        ];

        $provider = new UpsProvider($this->configuration, $upsData);

        $this->expectException(MissingUps::class);
        $this->expectExceptionMessage("UPS with ups_name 'wrong_id' not found.");

        $provider->getUps('wrong_id');
    }

    public function testGetProperties(): void
    {
        $provider = new UpsProvider($this->configuration, []);
        $properties = $provider->getProperties();

        $this->assertEquals(
            ['Name', 'Model Name', 'Serial Number', 'Status', 'Power', 'Battery'],
            $properties
        );
    }

    public function testConstructorWithNullSafeBatteryRuntimeThreshold(): void
    {
        $upsData = [
            [
                'name' => 'UPS No Threshold',
                'identifier' => 'ups_no_threshold',
                'host' => '192.168.1.100',
            ],
        ];

        $provider = new UpsProvider($this->configuration, $upsData);
        $ups = $provider->getUps('ups_no_threshold');

        $this->assertNull($ups->getSafeBatteryRuntimeThreshold());
    }

    public function testUpsListIsIndexedByIdentifier(): void
    {
        $upsData = [
            ['name' => 'First UPS', 'identifier' => 'first', 'host' => '192.168.1.1'],
            ['name' => 'Second UPS', 'identifier' => 'second', 'host' => '192.168.1.2'],
            ['name' => 'Third UPS', 'identifier' => 'third', 'host' => '192.168.1.3'],
        ];

        $provider = new UpsProvider($this->configuration, $upsData);
        $upsList = $provider->getUpsList();

        $this->assertArrayHasKey('first', $upsList);
        $this->assertArrayHasKey('second', $upsList);
        $this->assertArrayHasKey('third', $upsList);

        $this->assertEquals('First UPS', $upsList['first']->getName());
        $this->assertEquals('Second UPS', $upsList['second']->getName());
        $this->assertEquals('Third UPS', $upsList['third']->getName());
    }

    public function testUpdateAllUpsStatusCallsUpdateOnEachUps(): void
    {
        $upsMock1 = $this->createMock(UpsInterface::class);
        $upsMock1->expects($this->once())->method('updateStatus');

        $upsMock2 = $this->createMock(UpsInterface::class);
        $upsMock2->expects($this->once())->method('updateStatus');

        $provider = new UpsProvider($this->configuration, []);

        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('upsList');
        $property->setAccessible(true);
        $property->setValue($provider, ['ups1' => $upsMock1, 'ups2' => $upsMock2]);

        $provider->updateAllUpsStatus();
    }

    public function testIsAnyUpsOnBatteryReturnsTrueWhenOneUpsOnBattery(): void
    {
        $upsMock1 = $this->createMock(UpsInterface::class);
        $upsMock1->method('isOnBattery')->willReturn(false);

        $upsMock2 = $this->createMock(UpsInterface::class);
        $upsMock2->method('isOnBattery')->willReturn(true);

        $provider = new UpsProvider($this->configuration, []);

        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('upsList');
        $property->setAccessible(true);
        $property->setValue($provider, ['ups1' => $upsMock1, 'ups2' => $upsMock2]);

        $this->assertTrue($provider->isAnyUpsOnBattery());
    }

    public function testIsAnyUpsOnBatteryReturnsFalseWhenNoUpsOnBattery(): void
    {
        $upsMock1 = $this->createMock(UpsInterface::class);
        $upsMock1->method('isOnBattery')->willReturn(false);

        $upsMock2 = $this->createMock(UpsInterface::class);
        $upsMock2->method('isOnBattery')->willReturn(false);

        $provider = new UpsProvider($this->configuration, []);

        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('upsList');
        $property->setAccessible(true);
        $property->setValue($provider, ['ups1' => $upsMock1, 'ups2' => $upsMock2]);

        $this->assertFalse($provider->isAnyUpsOnBattery());
    }
}
