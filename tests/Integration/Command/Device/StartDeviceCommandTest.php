<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Device;

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
                "Device 'node-1' started: yes."
            );

        $tester = new CommandTester(new StartDeviceCommand($operations, $actionLogService));
        $exitCode = $tester->execute(['name' => 'node-1'], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Device 'node-1' started: yes.", $tester->getDisplay());
    }
}
