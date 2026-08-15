<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Device;

use EvilStudio\HAT\Command\Device\StartDeviceCommand;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class StartDeviceCommandTest extends TestCase
{
    public function testExecuteStartsDeviceAndCreatesActionLog(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $device = $this->createMock(DeviceInterface::class);

        $operations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($device);
        $device->expects($this->exactly(2))->method('getName')->willReturn('node-1');
        $operations->expects($this->once())->method('startDevice')->with('node-1')->willReturn(true);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'device.start',
                ActionLog::LEVEL_INFO,
                "Wake-on-LAN packet sent to device 'node-1'."
            );

        $tester = new CommandTester(new StartDeviceCommand($operations, $actionLogService));
        $exitCode = $tester->execute(['name' => 'node-1'], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Wake-on-LAN packet sent to device 'node-1'.", $tester->getDisplay());
    }

    public function testExecuteReturnsFailureAndWarnsWhenThePacketCouldNotBeSent(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $device = $this->createMock(DeviceInterface::class);

        $operations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($device);
        $device->expects($this->exactly(2))->method('getName')->willReturn('node-1');
        $operations->expects($this->once())->method('startDevice')->with('node-1')->willReturn(false);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'device.start',
                ActionLog::LEVEL_WARNING,
                "Wake-on-LAN packet could not be sent to device 'node-1'."
            );

        $tester = new CommandTester(new StartDeviceCommand($operations, $actionLogService));

        // Scripts chain on the exit code, so a failed action must not report success.
        $this->assertSame(Command::FAILURE, $tester->execute(['name' => 'node-1'], ['interactive' => false]));
    }

    public function testExecuteReportsNoDevicesInsteadOfCrashingOnEmptyDeviceList(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $operations->expects($this->once())->method('listDeviceNames')->willReturn([]);
        $operations->expects($this->never())->method('startDevice');

        $tester = new CommandTester(new StartDeviceCommand($operations, $actionLogService));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('No devices found.', $tester->getDisplay());
    }

    public function testExecuteReportsRequiredArgumentWhenNonInteractiveWithoutName(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $operations->expects($this->once())->method('listDeviceNames')->willReturn(['node-1']);
        $operations->expects($this->never())->method('startDevice');

        $tester = new CommandTester(new StartDeviceCommand($operations, $actionLogService));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString("Argument 'name' is required.", $tester->getDisplay());
    }
}
