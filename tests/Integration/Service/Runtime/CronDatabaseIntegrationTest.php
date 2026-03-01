<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Service\Runtime;

use DateTime;
use DateTimeZone;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Contract\UpsInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Repository\DeviceRepository;
use EvilStudio\HAT\Repository\ScheduleRepository;
use EvilStudio\HAT\Repository\UpsRepository;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Service\Runtime\Cron;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use EvilStudio\HAT\Service\Runtime\UpsRuntimeService;
use EvilStudio\HAT\Tests\Integration\Support\DatabaseIntegrationTestCase;

class CronDatabaseIntegrationTest extends DatabaseIntegrationTestCase
{
    protected DeviceService $deviceService;
    protected ScheduleService $scheduleService;

    protected function setUp(): void
    {
        parent::setUp();

        $deviceRepository = new DeviceRepository($this->entityManager);
        $upsRepository = new UpsRepository($this->entityManager);
        $scheduleRepository = new ScheduleRepository($this->entityManager);

        $this->deviceService = new DeviceService($this->entityManager, $deviceRepository, $upsRepository);
        $this->scheduleService = new ScheduleService($this->entityManager, $scheduleRepository, $deviceRepository);
    }

    public function testExecuteUsesSchedulesFromDatabaseAndStartsOfflineDevice(): void
    {
        $device = $this->deviceService->createDevice(
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::GENERIC->value
        );
        $this->scheduleService->createSchedule(
            'Night Start',
            '* * * * *',
            ScheduleInterface::COMMAND_START,
            [(int)$device->getId()],
            true
        );

        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $deviceOperationsService = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $capturedMessages = [];
        $this->captureLogs($actionLogService, $capturedMessages);

        $upsRuntimeService->expects($this->once())->method('updateAllUpsStatus');
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(false);
        $upsRuntimeService->expects($this->never())->method('isAnyUpsOnBattery');
        $configuration->expects($this->once())
            ->method('getCurrentDateTime')
            ->willReturn(new DateTime('2026-02-28 10:00:00', new DateTimeZone('UTC')));

        $deviceOperationsService->expects($this->once())
            ->method('getDevice')
            ->with('node-1')
            ->willReturn($runtimeDevice);
        $runtimeDevice->expects($this->once())->method('checkStatus');
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(false);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn(null);
        $runtimeDevice->expects($this->once())->method('start')->willReturn(true);

        $cron = new Cron(
            $deviceOperationsService,
            $upsRuntimeService,
            $this->scheduleService,
            $configuration,
            $actionLogService
        );
        $cron->execute();

        $this->assertContains('Schedule "Night Start" is matching.', $capturedMessages);
        $this->assertContains("Device 'node-1' started.", $capturedMessages);
    }

    public function testExecuteInBatteryModeStopsDeviceWhenDeviceThresholdExceedsUpsRuntime(): void
    {
        $upsService = new UpsService(
            $this->entityManager,
            new UpsRepository($this->entityManager)
        );
        $ups = $upsService->createUps('UPS Main', 'ups-main', 'ups.local', 300);

        $this->deviceService->createDevice(
            'node-4',
            '10.0.0.13',
            '00:11:22:33:44:58',
            DevicePlatform::GENERIC->value,
            null,
            1200,
            (int)$ups->getId()
        );

        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $runtimeUps = $this->createMock(UpsInterface::class);
        $deviceOperationsService = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $capturedMessages = [];
        $this->captureLogs($actionLogService, $capturedMessages);

        $upsRuntimeService->expects($this->once())->method('updateAllUpsStatus');
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $upsRuntimeService->expects($this->once())->method('isAnyUpsOnBattery')->willReturn(true);

        $deviceOperationsService->expects($this->once())
            ->method('listDevices')
            ->with(true)
            ->willReturn([$runtimeDevice]);
        $deviceOperationsService->expects($this->never())->method('getDevice');

        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-main');
        $runtimeDevice->expects($this->once())->method('getUpsLowBatteryRuntimeThreshold')->willReturn(1200);
        $runtimeDevice->expects($this->once())->method('getName')->willReturn('node-4');
        $runtimeDevice->expects($this->once())->method('stop')->willReturn(true);

        $upsRuntimeService->expects($this->once())
            ->method('getRuntimeUpsByIdentifier')
            ->with('ups-main')
            ->willReturn($runtimeUps);
        $runtimeUps->expects($this->once())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('isBatteryRuntimeLow')->willReturn(false);
        $runtimeUps->expects($this->once())->method('getBatteryRuntime')->willReturn(600);

        $cron = new Cron(
            $deviceOperationsService,
            $upsRuntimeService,
            $this->scheduleService,
            $configuration,
            $actionLogService
        );
        $cron->execute();

        $this->assertContains('[UPS Battery Mode enabled]', $capturedMessages);
        $this->assertContains(
            "Device 'node-4' stopped - UPS 'ups-main' has too low battery for this device.",
            $capturedMessages
        );
    }

    public function testExecuteInBatteryModeSkipsOnlineSchedulesEvenIfScheduleMatchesInDatabase(): void
    {
        $device = $this->deviceService->createDevice(
            'node-5',
            '10.0.0.14',
            '00:11:22:33:44:59',
            DevicePlatform::GENERIC->value
        );
        $this->scheduleService->createSchedule(
            'Should Not Run In Battery Mode',
            '* * * * *',
            ScheduleInterface::COMMAND_START,
            [(int)$device->getId()],
            true
        );

        $deviceOperationsService = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $capturedLogs = [];
        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->willReturnCallback(function (
                string $source,
                string $action,
                string $level,
                string $message
            ) use (&$capturedLogs): ActionLog {
                $capturedLogs[] = [$source, $action, $level, $message];

                return new ActionLog();
            });

        $upsRuntimeService->expects($this->once())->method('updateAllUpsStatus');
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $upsRuntimeService->expects($this->once())->method('isAnyUpsOnBattery')->willReturn(true);
        $configuration->expects($this->never())->method('getCurrentDateTime');

        $deviceOperationsService->expects($this->once())
            ->method('listDevices')
            ->with(true)
            ->willReturn([]);
        $deviceOperationsService->expects($this->never())->method('getDevice');

        $cron = new Cron(
            $deviceOperationsService,
            $upsRuntimeService,
            $this->scheduleService,
            $configuration,
            $actionLogService
        );
        $cron->execute();

        $this->assertCount(1, $capturedLogs);
        $this->assertSame(ActionLog::SOURCE_CRON, $capturedLogs[0][0]);
        $this->assertSame(ActionLogAction::CRON_EXECUTE->value, $capturedLogs[0][1]);
        $this->assertSame(ActionLog::LEVEL_WARNING, $capturedLogs[0][2]);
        $this->assertSame('[UPS Battery Mode enabled]', $capturedLogs[0][3]);
    }

    protected function captureLogs(ActionLogService $actionLogService, array &$capturedMessages): void
    {
        $actionLogService->expects($this->atLeastOnce())
            ->method('createActionLog')
            ->willReturnCallback(function (
                string $source,
                string $action,
                string $level,
                string $message
            ) use (&$capturedMessages): ActionLog {
                $this->assertSame(ActionLog::SOURCE_CRON, $source);
                $this->assertSame(ActionLogAction::CRON_EXECUTE->value, $action);
                $capturedMessages[] = $message;

                return new ActionLog();
            });
    }
}
