<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Device;

use EvilStudio\HAT\Command\Device\DeviceRemoveCommand;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DeviceRemoveCommandTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteRemovesDeviceWhenForced(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $device = $this->createDeviceEntity(1, 'node-1');

        $deviceService->expects($this->once())->method('getDeviceById')->with(1)->willReturn($device);
        $deviceService->expects($this->once())->method('removeDevice')->with(1);
        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'device.remove',
                ActionLog::LEVEL_WARNING,
                "Device 'node-1' removed. Removed schedule links: 0."
            );

        $tester = new CommandTester(new DeviceRemoveCommand($deviceService, $actionLogService));
        $exitCode = $tester->execute(['id' => '1', '--force' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Device 'node-1' removed.", $tester->getDisplay());
    }

    public function testExecuteFailsWithoutForceInNonInteractiveMode(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $device = $this->createDeviceEntity(1, 'node-1');

        $deviceService->expects($this->once())->method('getDeviceById')->with(1)->willReturn($device);
        $deviceService->expects($this->never())->method('removeDevice');
        $actionLogService->expects($this->never())->method('createActionLog');

        $tester = new CommandTester(new DeviceRemoveCommand($deviceService, $actionLogService));
        $exitCode = $tester->execute(['id' => '1'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Confirmation required', $tester->getDisplay());
    }
}
