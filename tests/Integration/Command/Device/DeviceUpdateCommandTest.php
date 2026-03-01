<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Device;

use EvilStudio\HAT\Command\Device\DeviceUpdateCommand;
use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DeviceUpdateCommandTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteUpdatesDevice(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $upsService = $this->createMock(UpsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $currentDevice = $this->createDeviceEntity(1, 'node-1');
        $updatedDevice = $this->createDeviceEntity(1, 'node-2');

        $deviceService->expects($this->once())->method('getDeviceById')->with(1)->willReturn($currentDevice);
        $deviceService->expects($this->once())
            ->method('updateDevice')
            ->with(
                1,
                'node-2',
                '10.0.0.11',
                '00:11:22:33:44:66',
                DevicePlatform::LINUX->value,
                'admin',
                120,
                2
            )
            ->willReturn($updatedDevice);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(ActionLog::SOURCE_CLI, 'device.update', ActionLog::LEVEL_INFO, "Device 'node-2' updated.");

        $tester = new CommandTester(new DeviceUpdateCommand($deviceService, $upsService, $actionLogService));
        $exitCode = $tester->execute([
            'id' => '1',
            '--name' => 'node-2',
            '--ip' => '10.0.0.11',
            '--mac' => '00:11:22:33:44:66',
            '--platform' => DevicePlatform::LINUX->value,
            '--username' => 'admin',
            '--ups-id' => '2',
            '--ups-low-battery-runtime-threshold' => '120',
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Device 'node-2' updated.", $tester->getDisplay());
    }
}
