<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Device;

use EvilStudio\HAT\Command\Device\StopDeviceCommand;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class StopDeviceCommandTest extends TestCase
{
    public function testExecuteStopsDeviceAndCreatesActionLog(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $device = $this->createMock(DeviceInterface::class);

        $operations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($device);
        $device->expects($this->exactly(2))->method('getName')->willReturn('node-1');
        $operations->expects($this->once())->method('stopDevice')->with('node-1')->willReturn(true);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'device.stop',
                ActionLog::LEVEL_INFO,
                "Device 'node-1' stopped: yes."
            );

        $tester = new CommandTester(new StopDeviceCommand($operations, $actionLogService));
        $exitCode = $tester->execute(['name' => 'node-1'], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Device 'node-1' stopped: yes.", $tester->getDisplay());
    }

    public function testExecuteReturnsFailureAndWarnsWhenTheDeviceDidNotStop(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $device = $this->createMock(DeviceInterface::class);

        $operations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($device);
        $device->expects($this->exactly(2))->method('getName')->willReturn('node-1');
        $operations->expects($this->once())->method('stopDevice')->with('node-1')->willReturn(false);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'device.stop',
                ActionLog::LEVEL_WARNING,
                "Device 'node-1' stopped: no."
            );

        $tester = new CommandTester(new StopDeviceCommand($operations, $actionLogService));

        // Scripts chain on the exit code, so a failed stop must not report success.
        $this->assertSame(Command::FAILURE, $tester->execute(['name' => 'node-1'], ['interactive' => false]));
    }
}
