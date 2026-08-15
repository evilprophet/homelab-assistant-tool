<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Runtime;

use DateTime;
use DateTimeZone;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Contract\DeviceAction;
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

        $upsRuntimeService->expects($this->once())
            ->method('pollAllUpsStatus')
            ->willReturn(['ups-main' => $runtimeUps]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $runtimeUps->expects($this->exactly(2))->method('isOnBattery')->willReturn(true);
        $deviceOperations->expects($this->once())->method('listDevices')->with(true)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('isAutoStopAllowed')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-main');
        $upsRuntimeService->expects($this->never())->method('getRuntimeUpsByIdentifier');
        $runtimeUps->expects($this->never())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('isBatteryRuntimeLow')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getName')->willReturn('node-1');
        $deviceOperations->expects($this->once())
            ->method('assertDeviceActionSupported')
            ->with($runtimeDevice, DeviceAction::STOP);
        $runtimeDevice->expects($this->once())->method('stop')->willReturn(true);
        $scheduleService->expects($this->never())->method('listEnabledSchedules');

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $this->assertContains('[UPS Battery Mode enabled]', array_column($capturedLogs, 'message'));
        $this->assertContains(
            "Device 'node-1' stopped - UPS 'ups-main' has low battery.",
            array_column($capturedLogs, 'message')
        );
    }

    public function testExecuteInBatteryModeLeavesDeviceRunningWhenItsOwnUpsIsOnMains(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $onBatteryUps = $this->createMock(UpsInterface::class);
        $onMainsUps = $this->createMock(UpsInterface::class);
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs, 2);

        $upsRuntimeService->expects($this->once())
            ->method('pollAllUpsStatus')
            ->willReturn(['ups-a' => $onBatteryUps, 'ups-b' => $onMainsUps]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $onBatteryUps->expects($this->once())->method('isOnBattery')->willReturn(true);
        $onMainsUps->expects($this->once())->method('isOnBattery')->willReturn(false);
        $onMainsUps->expects($this->never())->method('isBatteryRuntimeLow');
        $deviceOperations->expects($this->once())->method('listDevices')->with(true)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('isAutoStopAllowed')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-b');
        $runtimeDevice->expects($this->once())->method('getName')->willReturn('node-on-mains');
        $runtimeDevice->expects($this->never())->method('stop');
        $deviceOperations->expects($this->never())->method('assertDeviceActionSupported');

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $this->assertContains(
            "Device 'node-on-mains' left running - UPS 'ups-b' is on mains power.",
            array_column($capturedLogs, 'message')
        );
    }

    public function testExecuteInBatteryModeLogsErrorWhenStopFails(): void
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

        $upsRuntimeService->expects($this->once())
            ->method('pollAllUpsStatus')
            ->willReturn(['ups-main' => $runtimeUps]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $runtimeUps->expects($this->exactly(2))->method('isOnBattery')->willReturn(true);
        $deviceOperations->expects($this->once())->method('listDevices')->with(true)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('isAutoStopAllowed')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-main');
        $runtimeUps->expects($this->once())->method('isBatteryRuntimeLow')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getName')->willReturn('nas');
        $deviceOperations->expects($this->once())
            ->method('assertDeviceActionSupported')
            ->with($runtimeDevice, DeviceAction::STOP);
        $runtimeDevice->expects($this->once())->method('stop')->willReturn(false);

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $failureLog = array_values(array_filter(
            $capturedLogs,
            static fn (array $log): bool => $log['level'] === ActionLog::LEVEL_ERROR
        ));

        $this->assertCount(1, $failureLog);
        $this->assertSame("Device 'nas' FAILED to stop - UPS 'ups-main' has low battery.", $failureLog[0]['message']);
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

        $upsRuntimeService->expects($this->once())->method('pollAllUpsStatus')->willReturn([]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(false);
        $scheduleService->expects($this->once())->method('listEnabledSchedules')->willReturn([$schedule]);
        $configuration->expects($this->once())
            ->method('getCurrentDateTime')
            ->willReturn(new DateTime('2026-02-28 10:00:00', new DateTimeZone('UTC')));
        $deviceOperations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($runtimeDevice);
        $runtimeDevice->expects($this->once())->method('checkStatus');
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(false);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn(null);
        $deviceOperations->expects($this->once())
            ->method('assertDeviceActionSupported')
            ->with($runtimeDevice, DeviceAction::START);
        $runtimeDevice->expects($this->once())->method('start')->willReturn(true);

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
        $this->assertContains("Wake-on-LAN packet sent to device 'node-1'.", $infoMessages);
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

        $upsRuntimeService->expects($this->once())
            ->method('pollAllUpsStatus')
            ->willReturn(['ups-main' => $runtimeUps]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $runtimeUps->expects($this->exactly(2))->method('isOnBattery')->willReturn(true);
        $deviceOperations->expects($this->once())->method('listDevices')->with(true)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('isAutoStopAllowed')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-main');
        $upsRuntimeService->expects($this->never())->method('getRuntimeUpsByIdentifier');
        $runtimeUps->expects($this->never())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('isBatteryRuntimeLow')->willReturn(false);
        $runtimeDevice->expects($this->once())->method('getUpsLowBatteryRuntimeThreshold')->willReturn(1200);
        $runtimeUps->expects($this->once())->method('getBatteryRuntime')->willReturn(600);
        $runtimeDevice->expects($this->once())->method('getName')->willReturn('node-2');
        $deviceOperations->expects($this->once())
            ->method('assertDeviceActionSupported')
            ->with($runtimeDevice, DeviceAction::STOP);
        $runtimeDevice->expects($this->once())->method('stop')->willReturn(true);
        $scheduleService->expects($this->never())->method('listEnabledSchedules');

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $this->assertContains('[UPS Battery Mode enabled]', array_column($capturedLogs, 'message'));
        $this->assertContains(
            "Device 'node-2' stopped - UPS 'ups-main' has too low battery for this device.",
            array_column($capturedLogs, 'message')
        );
    }

    public function testExecuteInBatteryModeLeavesDeviceRunningWhenItsUpsStatusIsUnknown(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $onBatteryUps = $this->createMock(UpsInterface::class);
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs, 3);

        $upsRuntimeService->expects($this->once())
            ->method('pollAllUpsStatus')
            ->willReturn(['ups-a' => $onBatteryUps, 'ups-b' => null]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $onBatteryUps->expects($this->once())->method('isOnBattery')->willReturn(true);
        $deviceOperations->expects($this->once())->method('listDevices')->with(true)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('isAutoStopAllowed')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-b');
        $runtimeDevice->expects($this->once())->method('getName')->willReturn('node-3');
        $runtimeDevice->expects($this->never())->method('stop');
        $deviceOperations->expects($this->never())->method('assertDeviceActionSupported');
        $scheduleService->expects($this->never())->method('listEnabledSchedules');

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $messages = array_column($capturedLogs, 'message');
        $this->assertContains("UPS 'ups-b' status is unavailable - treated as unknown for this run.", $messages);
        $this->assertContains('[UPS Battery Mode enabled]', $messages);
        $this->assertContains("Device 'node-3' left running - UPS 'ups-b' status is unknown.", $messages);
    }

    public function testExecuteRunsSchedulesWhenEveryUpsStatusIsUnknown(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs);

        $upsRuntimeService->expects($this->once())->method('pollAllUpsStatus')->willReturn(['ups-main' => null]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $scheduleService->expects($this->once())->method('listEnabledSchedules')->willReturn([]);
        $deviceOperations->expects($this->never())->method('listDevices');

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $this->assertContains(
            "UPS 'ups-main' status is unavailable - treated as unknown for this run.",
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

        $upsRuntimeService->expects($this->once())
            ->method('pollAllUpsStatus')
            ->willReturn(['ups-main' => $runtimeUps]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(true);
        $runtimeUps->expects($this->exactly(2))->method('isOnBattery')->willReturn(true);
        $deviceOperations->expects($this->once())->method('listDevices')->with(true)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('isAutoStopAllowed')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getStatus')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getUpsIdentifier')->willReturn('ups-main');
        $upsRuntimeService->expects($this->never())->method('getRuntimeUpsByIdentifier');
        $runtimeUps->expects($this->never())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('isBatteryRuntimeLow')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('getName')->willReturn('node-unsupported');
        $deviceOperations->expects($this->once())
            ->method('assertDeviceActionSupported')
            ->with($runtimeDevice, DeviceAction::STOP)
            ->willThrowException(new UnsupportedDeviceAction("Stop action is not supported on 'synology_dsm' device."));
        $runtimeDevice->expects($this->never())->method('stop');
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
        $schedule = $this->createScheduleEntity(20, 'Unknown Task', '* * * * *');
        $this->forceEntityProperty($schedule, 'command', 'restart');
        $schedule->addDevice($this->createDeviceEntity(2, 'node-unknown'));
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs, 2);

        $upsRuntimeService->expects($this->once())->method('pollAllUpsStatus')->willReturn([]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(false);
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

    public function testExecuteSkipsAnInvalidCronExpressionAndKeepsProcessingOtherSchedules(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $brokenSchedule = $this->createScheduleEntity(30, 'Broken Task', '* * * * *');
        $this->forceEntityProperty($brokenSchedule, 'cronExpression', 'not a cron');
        $healthySchedule = $this->createScheduleEntity(31, 'Healthy Task', '* * * * *');
        $healthySchedule->addDevice($this->createDeviceEntity(3, 'node-healthy'));
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs, 3);

        $upsRuntimeService->expects($this->once())->method('pollAllUpsStatus')->willReturn([]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(false);
        $scheduleService->expects($this->once())
            ->method('listEnabledSchedules')
            ->willReturn([$brokenSchedule, $healthySchedule]);
        $configuration->expects($this->once())
            ->method('getCurrentDateTime')
            ->willReturn(new DateTime('2026-02-28 12:00:00', new DateTimeZone('UTC')));
        $deviceOperations->expects($this->once())
            ->method('getDevice')
            ->with('node-healthy')
            ->willThrowException(new RuntimeException('device lookup skipped'));

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        $messages = array_column($capturedLogs, 'message');
        $this->assertNotEmpty(array_filter(
            $messages,
            static fn (string $message): bool => str_contains($message, 'Schedule "Broken Task" skipped')
        ));
        $this->assertContains('Schedule "Healthy Task" is matching.', $messages);
    }

    public function testScheduleFiresAtItsExactMinute(): void
    {
        $capturedLogs = $this->runOnlineModeAt('0 2 * * *', '2026-02-28 02:00:00', true);

        $this->assertContains('Schedule "Night Start" is matching.', array_column($capturedLogs, 'message'));
    }

    public function testScheduleFiresOneMinuteEarlyBecauseOfTheDeliberateWindow(): void
    {
        $capturedLogs = $this->runOnlineModeAt('0 2 * * *', '2026-02-28 01:59:00', true);

        $this->assertContains('Schedule "Night Start" is matching.', array_column($capturedLogs, 'message'));
    }

    public function testScheduleFiresOneMinuteLateBecauseOfTheDeliberateWindow(): void
    {
        $capturedLogs = $this->runOnlineModeAt('0 2 * * *', '2026-02-28 02:01:00', true);

        $this->assertContains('Schedule "Night Start" is matching.', array_column($capturedLogs, 'message'));
    }

    public function testScheduleDoesNotFireOutsideTheWindow(): void
    {
        $this->assertSame([], $this->runOnlineModeAt('0 2 * * *', '2026-02-28 02:02:00', false));
    }

    public function testScheduleDoesNotFireBeforeTheWindow(): void
    {
        $this->assertSame([], $this->runOnlineModeAt('0 2 * * *', '2026-02-28 01:58:00', false));
    }

    public function testStopScheduleStopsARunningDevice(): void
    {
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $runtimeDevice->method('getStatus')->willReturn(true);
        $runtimeDevice->method('isAutoStopAllowed')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('stop')->willReturn(true);

        $capturedLogs = $this->runStopSchedule($runtimeDevice, true);

        $this->assertContains("Device 'node-1' stopped.", array_column($capturedLogs, 'message'));
    }

    public function testStopScheduleLogsErrorWhenTheDeviceFailsToStop(): void
    {
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $runtimeDevice->method('getStatus')->willReturn(true);
        $runtimeDevice->method('isAutoStopAllowed')->willReturn(true);
        $runtimeDevice->expects($this->once())->method('stop')->willReturn(false);

        $capturedLogs = $this->runStopSchedule($runtimeDevice, true);

        $failures = array_values(array_filter(
            $capturedLogs,
            static fn (array $log): bool => $log['level'] === ActionLog::LEVEL_ERROR
        ));

        $this->assertCount(1, $failures);
        $this->assertSame("Device 'node-1' FAILED to stop.", $failures[0]['message']);
    }

    public function testStopScheduleSkipsADeviceThatIsAlreadyStopped(): void
    {
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $runtimeDevice->method('getStatus')->willReturn(false);
        $runtimeDevice->expects($this->never())->method('stop');

        $capturedLogs = $this->runStopSchedule($runtimeDevice, false);

        $this->assertContains("Device 'node-1' already stopped.", array_column($capturedLogs, 'message'));
    }

    public function testStopScheduleSkipsADeviceWithAutoStopDisabled(): void
    {
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $runtimeDevice->method('getStatus')->willReturn(true);
        $runtimeDevice->method('isAutoStopAllowed')->willReturn(false);
        $runtimeDevice->expects($this->never())->method('stop');

        $capturedLogs = $this->runStopSchedule($runtimeDevice, false);

        $this->assertContains(
            "Device 'node-1' action skipped: auto-stop is disabled.",
            array_column($capturedLogs, 'message')
        );
    }

    protected function runStopSchedule(DeviceInterface $runtimeDevice, bool $expectsSupportCheck): array
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $schedule = $this->createScheduleEntity(11, 'Night Stop', '* * * * *', ScheduleInterface::COMMAND_STOP);
        $schedule->addDevice($this->createDeviceEntity(1, 'node-1'));
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs);

        $upsRuntimeService->expects($this->once())->method('pollAllUpsStatus')->willReturn([]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(false);
        $scheduleService->expects($this->once())->method('listEnabledSchedules')->willReturn([$schedule]);
        $configuration->expects($this->once())
            ->method('getCurrentDateTime')
            ->willReturn(new DateTime('2026-02-28 02:00:00', new DateTimeZone('UTC')));
        $deviceOperations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($runtimeDevice);
        $deviceOperations->expects($expectsSupportCheck ? $this->once() : $this->never())
            ->method('assertDeviceActionSupported')
            ->with($runtimeDevice, DeviceAction::STOP);

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        return $capturedLogs;
    }

    protected function runOnlineModeAt(
        string $cronExpression,
        string $currentDateTime,
        bool $expectsDeviceLookup
    ): array {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $scheduleService = $this->createMock(ScheduleService::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $schedule = $this->createScheduleEntity(10, 'Night Start', $cronExpression, ScheduleInterface::COMMAND_START);
        $schedule->addDevice($this->createDeviceEntity(1, 'node-1'));
        $capturedLogs = [];

        $this->captureLogs($actionLogService, $capturedLogs, $expectsDeviceLookup ? null : 0);

        $upsRuntimeService->expects($this->once())->method('pollAllUpsStatus')->willReturn([]);
        $configuration->expects($this->once())->method('isUpsModeEnabled')->willReturn(false);
        $scheduleService->expects($this->once())->method('listEnabledSchedules')->willReturn([$schedule]);
        $configuration->expects($this->once())
            ->method('getCurrentDateTime')
            ->willReturn(new DateTime($currentDateTime, new DateTimeZone('UTC')));

        if ($expectsDeviceLookup) {
            $deviceOperations->expects($this->once())->method('getDevice')->willReturn($runtimeDevice);
            $runtimeDevice->method('getStatus')->willReturn(true);
        } else {
            $deviceOperations->expects($this->never())->method('getDevice');
        }

        $cron = new Cron($deviceOperations, $upsRuntimeService, $scheduleService, $configuration, $actionLogService);
        $cron->execute();

        return $capturedLogs;
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
