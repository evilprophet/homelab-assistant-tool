<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Runtime;

use DateTime;
use DateTimeZone;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Contract\UpsInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Exception\UnsupportedDeviceAction;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Service\Runtime\Cron;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use EvilStudio\HAT\Service\Runtime\UpsRuntimeService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CronTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteInBatteryModeStopsDeviceWhenUpsBatteryIsLow(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $runtimeUps = $this->createMock(UpsInterface::class);
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs, 2);

        $upsRuntimeService->expects($this->once())->method('updateAllUpsStatus');
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $upsRuntimeService->expects($this->once())->method('isAnyUpsOnBattery')->willReturn(true);
        $deviceOperations->expects($this->once())->method('listDevices')->with(true)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-main');
        $upsRuntimeService->expects($this->once())
            ->method('getRuntimeUpsByIdentifier')
            ->with('ups-main')
            ->willReturn($runtimeUps);
        $runtimeUps->expects($this->once())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('isBatteryRuntimeLow')->willReturn(true);
        $runtimeDevice->expects($this->exactly(2))->method('getName')->willReturn('node-1');
        $deviceOperations->expects($this->once())->method('stopDevice')->with('node-1');
        $scheduleService->expects($this->never())->method('listEnabledSchedules');

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $this->assertContains('[UPS Battery Mode enabled]', array_column($capturedLogs, 'message'));
        $this->assertContains(
            "Device 'node-1' stopped - UPS 'ups-main' has low battery.",
            array_column($capturedLogs, 'message')
        );
    }

    public function testExecuteInOnlineModeStartsDeviceForDueSchedule(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $schedule = $this->createScheduleEntity(10, 'Night Start', '* * * * *', ScheduleInterface::COMMAND_START);
        $schedule->addDevice($this->createDeviceEntity(1, 'node-1'));
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs);

        $upsRuntimeService->expects($this->once())->method('updateAllUpsStatus');
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(false);
        $upsRuntimeService->expects($this->never())->method('isAnyUpsOnBattery');
        $scheduleService->expects($this->once())->method('listEnabledSchedules')->willReturn([$schedule]);
        $configuration->expects($this->once())
            ->method('getCurrentDateTime')
            ->willReturn(new DateTime('2026-02-28 10:00:00', new DateTimeZone('UTC')));
        $deviceOperations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($runtimeDevice);
        $runtimeDevice->expects($this->once())->method('checkStatus');
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(false);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn(null);
        $deviceOperations->expects($this->once())->method('startDevice')->with('node-1')->willReturn(true);

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $infoMessages = array_column(
            array_values(array_filter(
                $capturedLogs,
                static fn (array $log): bool => $log['level'] === ActionLog::LEVEL_INFO
            )),
            'message'
        );
        $this->assertContains('Schedule "Night Start" is matching.', $infoMessages);
        $this->assertContains("Device 'node-1' started.", $infoMessages);
    }

    public function testExecuteInBatteryModeStopsDeviceWhenDeviceThresholdExceedsUpsRuntime(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $runtimeUps = $this->createMock(UpsInterface::class);
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs, 2);

        $upsRuntimeService->expects($this->once())->method('updateAllUpsStatus');
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $upsRuntimeService->expects($this->once())->method('isAnyUpsOnBattery')->willReturn(true);
        $deviceOperations->expects($this->once())->method('listDevices')->with(true)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-main');
        $upsRuntimeService->expects($this->once())
            ->method('getRuntimeUpsByIdentifier')
            ->with('ups-main')
            ->willReturn($runtimeUps);
        $runtimeUps->expects($this->once())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('isBatteryRuntimeLow')->willReturn(false);
        $runtimeDevice->expects($this->once())->method('getUpsLowBatteryRuntimeThreshold')->willReturn(1200);
        $runtimeUps->expects($this->once())->method('getBatteryRuntime')->willReturn(600);
        $runtimeDevice->expects($this->exactly(2))->method('getName')->willReturn('node-2');
        $deviceOperations->expects($this->once())->method('stopDevice')->with('node-2');
        $scheduleService->expects($this->never())->method('listEnabledSchedules');

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $this->assertContains('[UPS Battery Mode enabled]', array_column($capturedLogs, 'message'));
        $this->assertContains(
            "Device 'node-2' stopped - UPS 'ups-main' has too low battery for this device.",
            array_column($capturedLogs, 'message')
        );
    }

    public function testExecuteInBatteryModeLogsErrorWhenUpsProcessingThrows(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs, 2);

        $upsRuntimeService->expects($this->once())->method('updateAllUpsStatus');
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $upsRuntimeService->expects($this->once())->method('isAnyUpsOnBattery')->willReturn(true);
        $deviceOperations->expects($this->once())->method('listDevices')->with(true)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-main');
        $upsRuntimeService->expects($this->once())
            ->method('getRuntimeUpsByIdentifier')
            ->with('ups-main')
            ->willThrowException(new RuntimeException('ups unavailable'));
        $runtimeDevice->expects($this->once())->method('getName')->willReturn('node-3');
        $runtimeDevice->expects($this->never())->method('stop');
        $scheduleService->expects($this->never())->method('listEnabledSchedules');

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $this->assertContains('[UPS Battery Mode enabled]', array_column($capturedLogs, 'message'));
        $this->assertContains(
            "Error processing device 'node-3': ups unavailable.",
            array_column($capturedLogs, 'message')
        );
    }

    public function testExecuteInBatteryModeSkipsUnsupportedStopAction(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $runtimeUps = $this->createMock(UpsInterface::class);
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs, 2);

        $upsRuntimeService->expects($this->once())->method('updateAllUpsStatus');
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $upsRuntimeService->expects($this->once())->method('isAnyUpsOnBattery')->willReturn(true);
        $deviceOperations->expects($this->once())->method('listDevices')->with(true)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-main');
        $upsRuntimeService->expects($this->once())
            ->method('getRuntimeUpsByIdentifier')
            ->with('ups-main')
            ->willReturn($runtimeUps);
        $runtimeUps->expects($this->once())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('isBatteryRuntimeLow')->willReturn(true);
        $runtimeDevice->expects($this->exactly(2))->method('getName')->willReturn('node-unsupported');
        $deviceOperations->expects($this->once())
            ->method('stopDevice')
            ->with('node-unsupported')
            ->willThrowException(new UnsupportedDeviceAction("Stop action is not supported on 'synology_dsm' device."));
        $scheduleService->expects($this->never())->method('listEnabledSchedules');

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $this->assertContains('[UPS Battery Mode enabled]', array_column($capturedLogs, 'message'));
        $this->assertContains(
            "Device 'node-unsupported' action skipped: Stop action is not supported on 'synology_dsm' device.",
            array_column($capturedLogs, 'message')
        );
    }

    public function testExecuteInOnlineModeLogsUnknownScheduleCommand(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $schedule = $this->createScheduleEntity(20, 'Unknown Task', '* * * * *', 'restart');
        $schedule->addDevice($this->createDeviceEntity(2, 'node-unknown'));
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs, 2);

        $upsRuntimeService->expects($this->once())->method('updateAllUpsStatus');
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(false);
        $upsRuntimeService->expects($this->never())->method('isAnyUpsOnBattery');
        $scheduleService->expects($this->once())->method('listEnabledSchedules')->willReturn([$schedule]);
        $configuration->expects($this->once())
            ->method('getCurrentDateTime')
            ->willReturn(new DateTime('2026-02-28 12:00:00', new DateTimeZone('UTC')));
        $deviceOperations->expects($this->never())->method('getDevice');

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $messages = array_column($capturedLogs, 'message');
        $this->assertContains('Schedule "Unknown Task" is matching.', $messages);
        $this->assertContains('Unknown command: restart.', $messages);
    }

    protected function captureLogs(
        ActionLogService $actionLogService,
        array &$capturedLogs,
        ?int $exactCalls = null
    ): void {
        $expectation = $exactCalls === null
            ? $this->atLeastOnce()
            : $this->exactly($exactCalls);

        $actionLogService->expects($expectation)
            ->method('createActionLog')
            ->willReturnCallback(function (
                string $source,
                string $action,
                string $level,
                string $message
            ) use (&$capturedLogs): ActionLog {
                $capturedLogs[] = [
                    'source' => $source,
                    'action' => $action,
                    'level' => $level,
                    'message' => $message,
                ];

                $this->assertSame(ActionLog::SOURCE_CRON, $source);
                $this->assertSame(ActionLogAction::CRON_EXECUTE->value, $action);

                return new ActionLog();
            });
    }
}
