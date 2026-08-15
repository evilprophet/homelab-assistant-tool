<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Device;

use EvilStudio\HAT\Command\Device\DeviceCreateCommand;
use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DeviceCreateCommandTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteCreatesDevice(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $upsService = $this->createStub(UpsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $createdDevice = $this->createDeviceEntity(10, 'node-1');

        $deviceService->expects($this->once())
            ->method('createDevice')
            ->with('node-1', '10.0.0.10', '00:11:22:33:44:55', DevicePlatform::GENERIC->value, null, null, null, true)
            ->willReturn($createdDevice);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'device.create',
                ActionLog::LEVEL_INFO,
                "Device 'node-1' created with ID 10."
            );

        $tester = new CommandTester(new DeviceCreateCommand($deviceService, $upsService, $actionLogService));
        $exitCode = $tester->execute([
            'name' => 'node-1',
            'ip' => '10.0.0.10',
            'mac' => '00:11:22:33:44:55',
            'platform' => DevicePlatform::GENERIC->value,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Device 'node-1' created with ID 10.", $tester->getDisplay());
    }

    public function testExecuteCreatesDeviceWithAutoStopDisabled(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $upsService = $this->createStub(UpsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $createdDevice = $this->createDeviceEntity(11, 'node-2');

        $deviceService->expects($this->once())
            ->method('createDevice')
            ->with('node-2', '10.0.0.20', '00:11:22:33:44:66', DevicePlatform::GENERIC->value, null, null, null, false)
            ->willReturn($createdDevice);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'device.create',
                ActionLog::LEVEL_INFO,
                "Device 'node-2' created with ID 11."
            );

        $tester = new CommandTester(new DeviceCreateCommand($deviceService, $upsService, $actionLogService));
        $exitCode = $tester->execute([
            'name' => 'node-2',
            'ip' => '10.0.0.20',
            'mac' => '00:11:22:33:44:66',
            'platform' => DevicePlatform::GENERIC->value,
            '--disallow-auto-stop' => true,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Device 'node-2' created with ID 11.", $tester->getDisplay());
    }
}
