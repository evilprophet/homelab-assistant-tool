<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service;

use EvilStudio\HAT\Api\DeviceInterface;
use EvilStudio\HAT\Api\ScheduleInterface;
use EvilStudio\HAT\Api\UpsInterface;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Provider\DeviceProvider;
use EvilStudio\HAT\Provider\ScheduleProvider;
use EvilStudio\HAT\Provider\UpsProvider;
use EvilStudio\HAT\Service\Cron;
use EvilStudio\HAT\Service\Logger;
use Exception;
use PHPUnit\Framework\TestCase;

class CronTest extends TestCase
{
    protected DeviceProvider $deviceProvider;
    protected ScheduleProvider $scheduleProvider;
    protected UpsProvider $upsProvider;
    protected Configuration $configuration;
    protected Logger $logger;

    protected function setUp(): void
    {
        $this->deviceProvider = $this->createMock(DeviceProvider::class);
        $this->scheduleProvider = $this->createMock(ScheduleProvider::class);
        $this->upsProvider = $this->createMock(UpsProvider::class);
        $this->configuration = $this->createMock(Configuration::class);
        $this->logger = $this->createMock(Logger::class);
    }

    protected function createCron(): Cron
    {
        return new Cron(
            $this->deviceProvider,
            $this->scheduleProvider,
            $this->upsProvider,
            $this->configuration,
            $this->logger
        );
    }

    public function testConstructor(): void
    {
        $cron = $this->createCron();

        $this->assertInstanceOf(Cron::class, $cron);
    }

    public function testExecuteCallsUpdateAllUpsStatus(): void
    {
        $this->upsProvider->expects($this->once())
            ->method('updateAllUpsStatus');

        $this->configuration->method('isUpsModeEnabled')->willReturn(false);
        $this->scheduleProvider->method('getScheduleList')->willReturn([]);

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testExecuteWithUpsModeDisabledCallsHandleOnlineMode(): void
    {
        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(false);

        $this->scheduleProvider->expects($this->once())
            ->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([]);

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testExecuteWithUpsModeEnabledButNoUpsOnBatteryCallsHandleOnlineMode(): void
    {
        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(true);
        $this->upsProvider->method('isAnyUpsOnBattery')->willReturn(false);

        $this->scheduleProvider->expects($this->once())
            ->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([]);

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testExecuteWithUpsModeEnabledAndUpsOnBatteryCallsHandleBatteryMode(): void
    {
        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(true);
        $this->upsProvider->method('isAnyUpsOnBattery')->willReturn(true);

        $this->logger->expects($this->once())
            ->method('logWarning')
            ->with('[UPS Battery Mode enabled]');

        $this->deviceProvider->expects($this->once())
            ->method('checkAllDevicesStatus');
        $this->deviceProvider->method('getDeviceList')->willReturn([]);

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testHandleBatteryModeStopsDeviceWhenBatteryRuntimeLow(): void
    {
        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getStatus')->willReturn(true);
        $deviceMock->method('getUpsIdentifier')->willReturn('ups1');
        $deviceMock->method('getName')->willReturn('Test Device');
        $deviceMock->expects($this->once())->method('stop');

        $upsMock = $this->createMock(UpsInterface::class);
        $upsMock->method('isBatteryRuntimeLow')->willReturn(true);

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(true);
        $this->upsProvider->method('isAnyUpsOnBattery')->willReturn(true);
        $this->upsProvider->method('getUps')->with('ups1')->willReturn($upsMock);

        $this->deviceProvider->method('checkAllDevicesStatus');
        $this->deviceProvider->method('getDeviceList')->willReturn(['Test Device' => $deviceMock]);

        $this->logger->expects($this->atLeastOnce())
            ->method('logInfo')
            ->with($this->stringContains('stopped - UPS'));

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testHandleBatteryModeStopsDeviceWhenDeviceThresholdExceeded(): void
    {
        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getStatus')->willReturn(true);
        $deviceMock->method('getUpsIdentifier')->willReturn('ups1');
        $deviceMock->method('getName')->willReturn('Test Device');
        $deviceMock->method('getUpsLowBatteryRuntimeThreshold')->willReturn(600);
        $deviceMock->expects($this->once())->method('stop');

        $upsMock = $this->createMock(UpsInterface::class);
        $upsMock->method('isBatteryRuntimeLow')->willReturn(false);
        $upsMock->method('getBatteryRuntime')->willReturn(300);

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(true);
        $this->upsProvider->method('isAnyUpsOnBattery')->willReturn(true);
        $this->upsProvider->method('getUps')->with('ups1')->willReturn($upsMock);

        $this->deviceProvider->method('checkAllDevicesStatus');
        $this->deviceProvider->method('getDeviceList')->willReturn(['Test Device' => $deviceMock]);

        $this->logger->expects($this->atLeastOnce())
            ->method('logInfo')
            ->with($this->stringContains('too low battery for this device'));

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testHandleBatteryModeSkipsOfflineDevices(): void
    {
        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getStatus')->willReturn(false);
        $deviceMock->expects($this->never())->method('stop');

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(true);
        $this->upsProvider->method('isAnyUpsOnBattery')->willReturn(true);

        $this->deviceProvider->method('checkAllDevicesStatus');
        $this->deviceProvider->method('getDeviceList')->willReturn(['Test Device' => $deviceMock]);

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testHandleBatteryModeSkipsDevicesWithoutUps(): void
    {
        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getStatus')->willReturn(true);
        $deviceMock->method('getUpsIdentifier')->willReturn(null);
        $deviceMock->expects($this->never())->method('stop');

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(true);
        $this->upsProvider->method('isAnyUpsOnBattery')->willReturn(true);

        $this->deviceProvider->method('checkAllDevicesStatus');
        $this->deviceProvider->method('getDeviceList')->willReturn(['Test Device' => $deviceMock]);

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testHandleBatteryModeLogsErrorOnException(): void
    {
        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getStatus')->willReturn(true);
        $deviceMock->method('getUpsIdentifier')->willReturn('ups1');
        $deviceMock->method('getName')->willReturn('Test Device');

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(true);
        $this->upsProvider->method('isAnyUpsOnBattery')->willReturn(true);
        $this->upsProvider->method('getUps')->willThrowException(new Exception('UPS not found'));

        $this->deviceProvider->method('checkAllDevicesStatus');
        $this->deviceProvider->method('getDeviceList')->willReturn(['Test Device' => $deviceMock]);

        $this->logger->expects($this->atLeastOnce())
            ->method('logError')
            ->with($this->stringContains('Error processing device'));

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testHandleOnlineModeExecutesMatchingSchedules(): void
    {
        $scheduleMock = $this->createMock(ScheduleInterface::class);
        $scheduleMock->method('isCronScheduleMatching')->willReturn(true);
        $scheduleMock->method('getName')->willReturn('Test Schedule');
        $scheduleMock->method('getCommand')->willReturn('start');
        $scheduleMock->method('getDeviceCodes')->willReturn(['device1']);

        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getName')->willReturn('device1');
        $deviceMock->method('getStatus')->willReturn(false);
        $deviceMock->method('getUpsIdentifier')->willReturn('ups1');
        $deviceMock->expects($this->once())->method('checkStatus');
        $deviceMock->expects($this->once())->method('start');

        $upsMock = $this->createMock(UpsInterface::class);
        $upsMock->method('getSafeBatteryRuntimeThreshold')->willReturn(null);

        $this->upsProvider->method('updateAllUpsStatus');
        $this->upsProvider->method('getUps')->with('ups1')->willReturn($upsMock);
        $this->configuration->method('isUpsModeEnabled')->willReturn(false);

        $this->scheduleProvider->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([$scheduleMock]);
        $this->deviceProvider->method('getDevice')->with('device1')->willReturn($deviceMock);

        $this->logger->expects($this->exactly(2))
            ->method('logInfo');

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testHandleOnlineModeSkipsNonMatchingSchedules(): void
    {
        $scheduleMock = $this->createMock(ScheduleInterface::class);
        $scheduleMock->method('isCronScheduleMatching')->willReturn(false);

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(false);

        $this->scheduleProvider->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([$scheduleMock]);

        $this->deviceProvider->expects($this->never())->method('getDevice');

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testCommandStartSkipsAlreadyRunningDevices(): void
    {
        $scheduleMock = $this->createMock(ScheduleInterface::class);
        $scheduleMock->method('isCronScheduleMatching')->willReturn(true);
        $scheduleMock->method('getName')->willReturn('Test Schedule');
        $scheduleMock->method('getCommand')->willReturn('start');
        $scheduleMock->method('getDeviceCodes')->willReturn(['device1']);

        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getStatus')->willReturn(true);
        $deviceMock->method('getName')->willReturn('device1');
        $deviceMock->expects($this->once())->method('checkStatus');
        $deviceMock->expects($this->never())->method('start');

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(false);

        $this->scheduleProvider->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([$scheduleMock]);
        $this->deviceProvider->method('getDevice')->with('device1')->willReturn($deviceMock);

        $this->logger->expects($this->exactly(2))
            ->method('logInfo');

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testCommandStartSkipsWhenUpsBatteryTooLow(): void
    {
        $scheduleMock = $this->createMock(ScheduleInterface::class);
        $scheduleMock->method('isCronScheduleMatching')->willReturn(true);
        $scheduleMock->method('getName')->willReturn('Test Schedule');
        $scheduleMock->method('getCommand')->willReturn('start');
        $scheduleMock->method('getDeviceCodes')->willReturn(['device1']);

        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getStatus')->willReturn(false);
        $deviceMock->method('getName')->willReturn('device1');
        $deviceMock->method('getUpsIdentifier')->willReturn('ups1');
        $deviceMock->expects($this->once())->method('checkStatus');
        $deviceMock->expects($this->never())->method('start');

        $upsMock = $this->createMock(UpsInterface::class);
        $upsMock->method('getSafeBatteryRuntimeThreshold')->willReturn(600);
        $upsMock->method('getBatteryRuntime')->willReturn(300);

        $this->upsProvider->method('updateAllUpsStatus');
        $this->upsProvider->method('getUps')->with('ups1')->willReturn($upsMock);
        $this->configuration->method('isUpsModeEnabled')->willReturn(false);

        $this->scheduleProvider->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([$scheduleMock]);
        $this->deviceProvider->method('getDevice')->with('device1')->willReturn($deviceMock);

        $this->logger->expects($this->exactly(2))
            ->method('logInfo');

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testCommandStopSkipsAlreadyStoppedDevices(): void
    {
        $scheduleMock = $this->createMock(ScheduleInterface::class);
        $scheduleMock->method('isCronScheduleMatching')->willReturn(true);
        $scheduleMock->method('getName')->willReturn('Test Schedule');
        $scheduleMock->method('getCommand')->willReturn('stop');
        $scheduleMock->method('getDeviceCodes')->willReturn(['device1']);

        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getStatus')->willReturn(false);
        $deviceMock->method('getName')->willReturn('device1');
        $deviceMock->expects($this->once())->method('checkStatus');
        $deviceMock->expects($this->never())->method('stop');

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(false);

        $this->scheduleProvider->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([$scheduleMock]);
        $this->deviceProvider->method('getDevice')->with('device1')->willReturn($deviceMock);

        $this->logger->expects($this->exactly(2))
            ->method('logInfo');

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testCommandStopStopsRunningDevice(): void
    {
        $scheduleMock = $this->createMock(ScheduleInterface::class);
        $scheduleMock->method('isCronScheduleMatching')->willReturn(true);
        $scheduleMock->method('getName')->willReturn('Test Schedule');
        $scheduleMock->method('getCommand')->willReturn('stop');
        $scheduleMock->method('getDeviceCodes')->willReturn(['device1']);

        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getStatus')->willReturn(true);
        $deviceMock->method('getName')->willReturn('device1');
        $deviceMock->expects($this->once())->method('stop');

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(false);

        $this->scheduleProvider->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([$scheduleMock]);
        $this->deviceProvider->method('getDevice')->with('device1')->willReturn($deviceMock);

        $this->logger->expects($this->atLeastOnce())
            ->method('logInfo')
            ->with($this->logicalOr(
                $this->stringContains('matching'),
                $this->stringContains('stopped')
            ));

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testUnknownCommandLogsInfo(): void
    {
        $scheduleMock = $this->createMock(ScheduleInterface::class);
        $scheduleMock->method('isCronScheduleMatching')->willReturn(true);
        $scheduleMock->method('getName')->willReturn('Test Schedule');
        $scheduleMock->method('getCommand')->willReturn('unknown');

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(false);

        $this->scheduleProvider->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([$scheduleMock]);

        $this->logger->expects($this->exactly(2))
            ->method('logInfo');

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testCommandStartHandlesExceptions(): void
    {
        $scheduleMock = $this->createMock(ScheduleInterface::class);
        $scheduleMock->method('isCronScheduleMatching')->willReturn(true);
        $scheduleMock->method('getName')->willReturn('Test Schedule');
        $scheduleMock->method('getCommand')->willReturn('start');
        $scheduleMock->method('getDeviceCodes')->willReturn(['device1']);

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(false);

        $this->scheduleProvider->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([$scheduleMock]);
        $this->deviceProvider->method('getDevice')->willThrowException(new Exception('Device not found'));

        $this->logger->expects($this->atLeastOnce())
            ->method('logError')
            ->with($this->stringContains('Device not found'));

        $cron = $this->createCron();
        $cron->execute();
    }

    public function testCommandStopHandlesExceptions(): void
    {
        $scheduleMock = $this->createMock(ScheduleInterface::class);
        $scheduleMock->method('isCronScheduleMatching')->willReturn(true);
        $scheduleMock->method('getName')->willReturn('Test Schedule');
        $scheduleMock->method('getCommand')->willReturn('stop');
        $scheduleMock->method('getDeviceCodes')->willReturn(['device1']);

        $this->upsProvider->method('updateAllUpsStatus');
        $this->configuration->method('isUpsModeEnabled')->willReturn(false);

        $this->scheduleProvider->method('checkAllCronSchedule');
        $this->scheduleProvider->method('getScheduleList')->willReturn([$scheduleMock]);
        $this->deviceProvider->method('getDevice')->willThrowException(new Exception('Device not found'));

        $this->logger->expects($this->atLeastOnce())
            ->method('logError')
            ->with($this->stringContains('Device not found'));

        $cron = $this->createCron();
        $cron->execute();
    }
}
