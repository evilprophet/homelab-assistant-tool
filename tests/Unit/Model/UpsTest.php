<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Model;

use EvilStudio\HAT\Exception\UpsFailedUpdateStatus;
use EvilStudio\HAT\Model\Ups;
use PHPUnit\Framework\TestCase;

class UpsTest extends TestCase
{
    protected function createUpsWithMockedCommand(array $mockOutput, int $mockResultCode = 0): Ups
    {
        $ups = $this->getMockBuilder(Ups::class)
            ->setConstructorArgs([
                'name' => 'Test UPS',
                'identifier' => 'ups1',
                'host' => '192.168.1.100',
                'safeBatteryRuntimeThreshold' => 600
            ])
            ->onlyMethods(['executeCommand'])
            ->getMock();

        $ups->expects($this->once())
            ->method('executeCommand')
            ->willReturnCallback(function ($command, &$output, &$resultCode) use ($mockOutput, $mockResultCode) {
                $output = $mockOutput;
                $resultCode = $mockResultCode;
            });

        return $ups;
    }

    public function testConstructorAndGetters(): void
    {
        $ups = new Ups(
            name: 'Test UPS',
            identifier: 'ups1',
            host: '192.168.1.100',
            safeBatteryRuntimeThreshold: 600
        );

        $this->assertEquals('Test UPS', $ups->getName());
        $this->assertEquals('ups1', $ups->getIdentifier());
        $this->assertEquals('192.168.1.100', $ups->getHost());
        $this->assertEquals(600, $ups->getSafeBatteryRuntimeThreshold());
    }

    public function testConstructorWithNullSafeThreshold(): void
    {
        $ups = new Ups(
            name: 'Test UPS',
            identifier: 'ups1',
            host: '192.168.1.100',
            safeBatteryRuntimeThreshold: null
        );

        $this->assertNull($ups->getSafeBatteryRuntimeThreshold());
    }

    public function testUpdateStatusParsesUpscOutputSuccessfully(): void
    {
        $mockOutput = [
            'device.model: APC Smart-UPS 1000',
            'device.serial: ABC123456',
            'ups.status: OL',
            'ups.power: 100',
            'ups.realpower: 80',
            'battery.charge: 95',
            'battery.runtime: 1800',
            'battery.runtime.low: 300',
        ];

        $ups = $this->createUpsWithMockedCommand($mockOutput);
        $ups->updateStatus();

        $this->assertEquals('APC Smart-UPS 1000', $ups->getModelName());
        $this->assertEquals('ABC123456', $ups->getSerialNumber());
        $this->assertEquals('OL', $ups->getStatus());
        $this->assertEquals(100, $ups->getPower());
        $this->assertEquals(80, $ups->getRealPower());
        $this->assertEquals(95, $ups->getBatteryLevel());
        $this->assertEquals(1800, $ups->getBatteryRuntime());
        $this->assertEquals(300, $ups->getLowBatteryRuntimeThreshold());
    }

    public function testUpdateStatusHandlesPartialData(): void
    {
        $mockOutput = [
            'device.model: Basic UPS',
            'ups.status: OL',
            'battery.charge: 50',
        ];

        $ups = $this->createUpsWithMockedCommand($mockOutput);
        $ups->updateStatus();

        $this->assertEquals('Basic UPS', $ups->getModelName());
        $this->assertEquals('OL', $ups->getStatus());
        $this->assertEquals(50, $ups->getBatteryLevel());
        $this->assertNull($ups->getPower());
        $this->assertNull($ups->getRealPower());
        $this->assertNull($ups->getBatteryRuntime());
    }

    public function testUpdateStatusIgnoresLinesWithoutColon(): void
    {
        $mockOutput = [
            'device.model: Test Model',
            'Invalid line without colon',
            'ups.status: OL',
            'Another invalid line',
        ];

        $ups = $this->createUpsWithMockedCommand($mockOutput);
        $ups->updateStatus();

        $this->assertEquals('Test Model', $ups->getModelName());
        $this->assertEquals('OL', $ups->getStatus());
    }

    public function testUpdateStatusThrowsExceptionOnCommandFailure(): void
    {
        $ups = $this->createUpsWithMockedCommand([], 1);

        $this->expectException(UpsFailedUpdateStatus::class);
        $this->expectExceptionMessage("Cannot load status for UPS 'ups1' at '192.168.1.100'.");

        $ups->updateStatus();
    }

    public function testUpdateStatusHandlesEmptyOutput(): void
    {
        $ups = $this->createUpsWithMockedCommand([]);
        $ups->updateStatus();

        $this->assertEquals('', $ups->getModelName());
        $this->assertEquals('', $ups->getSerialNumber());
        $this->assertEquals('', $ups->getStatus());
    }

    public function testIsOnBatteryWhenStatusContainsOB(): void
    {
        $ups = $this->createUpsWithMockedCommand(['ups.status: OL OB']);
        $ups->updateStatus();

        $this->assertTrue($ups->isOnBattery());
    }

    public function testIsOnBatteryWhenStatusDoesNotContainOB(): void
    {
        $ups = $this->createUpsWithMockedCommand(['ups.status: OL']);
        $ups->updateStatus();

        $this->assertFalse($ups->isOnBattery());
    }

    public function testIsBatteryRuntimeLowWhenBelowThreshold(): void
    {
        $mockOutput = [
            'battery.runtime: 300',
            'battery.runtime.low: 600',
        ];

        $ups = $this->createUpsWithMockedCommand($mockOutput);
        $ups->updateStatus();

        $this->assertTrue($ups->isBatteryRuntimeLow());
    }

    public function testIsBatteryRuntimeLowWhenAboveThreshold(): void
    {
        $mockOutput = [
            'battery.runtime: 900',
            'battery.runtime.low: 600',
        ];

        $ups = $this->createUpsWithMockedCommand($mockOutput);
        $ups->updateStatus();

        $this->assertFalse($ups->isBatteryRuntimeLow());
    }

    public function testToArrayWithAllProperties(): void
    {
        $mockOutput = [
            'device.model: APC Smart-UPS 1000',
            'device.serial: ABC123456',
            'ups.status: OL',
            'ups.power: 100',
            'ups.realpower: 80',
            'battery.charge: 95',
            'battery.runtime: 1800',
            'battery.runtime.low: 300',
        ];

        $ups = $this->createUpsWithMockedCommand($mockOutput);
        $ups->updateStatus();
        $result = $ups->toArray();

        $this->assertEquals('Test UPS', $result['name']);
        $this->assertEquals('APC Smart-UPS 1000', $result['model_name']);
        $this->assertEquals('ABC123456', $result['serial_number']);
        $this->assertEquals('OL', $result['status']);
        $this->assertStringContainsString('80 W', $result['power']);
        $this->assertStringContainsString('100 VA', $result['power']);
        $this->assertStringContainsString('95%', $result['battery']);
        $this->assertStringContainsString('30 min', $result['battery']);
        $this->assertStringContainsString('5 min', $result['battery']);
        $this->assertStringContainsString('10 min', $result['battery']);
    }

    public function testToArrayWithNullValues(): void
    {
        $ups = new Ups(
            name: 'Test UPS',
            identifier: 'ups1',
            host: '192.168.1.100',
            safeBatteryRuntimeThreshold: null
        );

        $result = $ups->toArray();

        $this->assertEquals('Test UPS', $result['name']);
        $this->assertStringContainsString('-', $result['power']);
        $this->assertStringContainsString('N/A', $result['battery']);
    }
}
