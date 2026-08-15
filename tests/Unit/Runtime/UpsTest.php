<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Runtime;

use EvilStudio\HAT\Exception\UpsFailedUpdateStatus;
use EvilStudio\HAT\Runtime\Ups;
use PHPUnit\Framework\TestCase;

class UpsTest extends TestCase
{
    public function testUpdateStatusBuildsExpectedUpsTargetArgument(): void
    {
        $runtimeUps = new class (1, 'Main UPS', 'ups-main', 'ups.local', 900, []) extends Ups {
            public ?string $capturedTarget = null;

            protected function executeCommand(string $target, array &$output, int &$resultCode): void
            {
                $this->capturedTarget = $target;
                $resultCode = 0;
                $output = [];
            }
        };

        $runtimeUps->updateStatus();

        $this->assertSame('ups-main@ups.local', $runtimeUps->capturedTarget);
    }

    public function testUpdateStatusLoadsPropertiesAndComputesBatteryFlags(): void
    {
        $runtimeUps = new class (1, 'Main UPS', 'ups-main', 'ups.local', 900, []) extends Ups {
            protected function executeCommand(string $target, array &$output, int &$resultCode): void
            {
                $resultCode = 0;
                $output = [
                    'device.model: Smart-UPS 1500',
                    'device.serial: ABC123',
                    'ups.status: OL OB',
                    'ups.power: 500',
                    'ups.realpower: 420',
                    'battery.runtime: 300',
                    'battery.runtime.low: 600',
                    'battery.charge: 55',
                ];
            }
        };

        $runtimeUps->updateStatus();
        $data = $runtimeUps->toArray();

        $this->assertSame('Smart-UPS 1500', $runtimeUps->getModelName());
        $this->assertSame('ABC123', $runtimeUps->getSerialNumber());
        $this->assertTrue($runtimeUps->isOnBattery());
        $this->assertTrue($runtimeUps->isBatteryRuntimeLow());
        $this->assertSame('On Battery', $data['status']);
        $this->assertStringContainsString('Current: 55%', $data['battery']);
        $this->assertStringContainsString('Safe Runtime Threshold: 15 min', $data['battery']);
    }

    public function testExhaustedBatteryRuntimeIsReportedAsLow(): void
    {
        $runtimeUps = $this->createRuntimeUps(['battery.runtime: 0', 'battery.runtime.low: 600']);

        $runtimeUps->updateStatus();

        $this->assertTrue($runtimeUps->isBatteryRuntimeLow());
        $this->assertStringContainsString('Runtime: 0 min', $runtimeUps->toArray()['battery']);
    }

    public function testZeroLowRuntimeThresholdIsTreatedAsRealThreshold(): void
    {
        $runtimeUps = $this->createRuntimeUps(['battery.runtime: 0', 'battery.runtime.low: 0']);

        $runtimeUps->updateStatus();

        $this->assertTrue($runtimeUps->isBatteryRuntimeLow());
        $this->assertStringContainsString('Low Runtime Threshold: 0 min', $runtimeUps->toArray()['battery']);
    }

    public function testBatteryRuntimeEqualToThresholdIsLow(): void
    {
        $runtimeUps = $this->createRuntimeUps(['battery.runtime: 600', 'battery.runtime.low: 600']);

        $runtimeUps->updateStatus();

        $this->assertTrue($runtimeUps->isBatteryRuntimeLow());
    }

    public function testMissingBatteryRuntimeIsNotLow(): void
    {
        $runtimeUps = $this->createRuntimeUps(['battery.runtime.low: 600']);

        $runtimeUps->updateStatus();

        $this->assertFalse($runtimeUps->isBatteryRuntimeLow());
        $this->assertStringContainsString('Runtime: N/A min', $runtimeUps->toArray()['battery']);
    }

    public function testMissingLowRuntimeThresholdIsNotLow(): void
    {
        $runtimeUps = $this->createRuntimeUps(['battery.runtime: 0']);

        $runtimeUps->updateStatus();

        $this->assertFalse($runtimeUps->isBatteryRuntimeLow());
    }

    public function testUpdateStatusThrowsWhenCommandFails(): void
    {
        $runtimeUps = new class (1, 'Main UPS', 'ups-main', 'ups.local', 900, []) extends Ups {
            protected function executeCommand(string $target, array &$output, int &$resultCode): void
            {
                $resultCode = 1;
                $output = [];
            }
        };

        $this->expectException(UpsFailedUpdateStatus::class);
        $this->expectExceptionMessage("Cannot load status for UPS 'ups-main' at 'ups.local'.");

        $runtimeUps->updateStatus();
    }

    public function testRepeatedPollDoesNotServeValuesFromThePreviousOne(): void
    {
        $runtimeUps = new class (1, 'Main UPS', 'ups-main', 'ups.local', 900, []) extends Ups {
            public array $nextOutput = [];

            protected function executeCommand(string $target, array &$output, int &$resultCode): void
            {
                $resultCode = 0;
                $output = $this->nextOutput;
            }
        };

        $runtimeUps->nextOutput = ['device.model: Smart-UPS 1500', 'ups.status: OB', 'battery.charge: 55'];
        $runtimeUps->updateStatus();
        $this->assertTrue($runtimeUps->isOnBattery());
        $this->assertSame(55, $runtimeUps->getBatteryLevel());

        $runtimeUps->nextOutput = ['ups.status: OL'];
        $runtimeUps->updateStatus();

        $this->assertFalse($runtimeUps->isOnBattery());
        $this->assertNull($runtimeUps->getBatteryLevel());
        $this->assertSame('-', $runtimeUps->toArray()['model_name']);
    }

    protected function createRuntimeUps(array $statusOutput): Ups
    {
        return new class (1, 'Main UPS', 'ups-main', 'ups.local', 900, [], $statusOutput) extends Ups {
            public function __construct(
                ?int $id,
                string $name,
                string $identifier,
                string $host,
                ?int $safeBatteryRuntimeThreshold,
                array $linkedDevices,
                protected array $statusOutput
            ) {
                parent::__construct($id, $name, $identifier, $host, $safeBatteryRuntimeThreshold, $linkedDevices);
            }

            protected function executeCommand(string $target, array &$output, int &$resultCode): void
            {
                $resultCode = 0;
                $output = $this->statusOutput;
            }
        };
    }
}
