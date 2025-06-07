<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service;

use EvilStudio\HAT\Api\ScheduleInterface;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Provider\DeviceProvider;
use EvilStudio\HAT\Provider\ScheduleProvider;
use EvilStudio\HAT\Provider\UpsProvider;
use Exception;

class Cron
{
    public function __construct(
        protected DeviceProvider $deviceProvider,
        protected ScheduleProvider $scheduleProvider,
        protected UpsProvider $upsProvider,
        protected Configuration $configuration,
        protected Logger $logger
    ) {
    }

    public function execute(): void
    {
        $this->upsProvider->updateAllUpsStatus();

        if ($this->configuration->isUpsModeEnabled() && $this->upsProvider->isAnyUpsOnBattery()) {
            $this->handleBatteryMode();

            return;
        }

        $this->handleOnlineMode();
    }

    protected function handleBatteryMode(): void
    {
        $this->logger->logWarning('[UPS Battery Mode enabled]');

        $this->deviceProvider->checkAllDevicesStatus();
        $deviceList = $this->deviceProvider->getDeviceList();

        foreach ($deviceList as $device) {
            if (!$device->getStatus()) {
                continue;
            }

            $upsIdentifier = $device->getUpsIdentifier();
            if (empty($upsIdentifier)) {
                continue;
            }

            try {
                $ups = $this->upsProvider->getUps($upsIdentifier);

                if (!$ups->isBatteryRuntimeLow()) {
                    $this->logger->logInfo(
                        sprintf(
                            'Device %s is running - UPS %s has remaining runtime: %s minutes',
                            $device->getName(),
                            $upsIdentifier,
                            round($ups->getBatteryRuntime() / 60)
                        )
                    );
                    continue;
                }

                $device->stop();
                $this->logger->logInfo(sprintf('Device %s stopped - UPS %s has low battery', $device->getName(), $upsIdentifier));
            } catch (Exception $e) {
                $this->logger->logError(sprintf('Error processing device %s: %s', $device->getName(), $e->getMessage()));
            }
        }
    }

    protected function handleOnlineMode(): void
    {
        $this->scheduleProvider->checkAllCronSchedule();
        $schedules = $this->scheduleProvider->getScheduleList();

        foreach ($schedules as $schedule) {
            if (!$schedule->isCronScheduleMatching()) {
                continue;
            }

            $message = sprintf('Schedule "%s" is matching', $schedule->getName());
            $this->logger->logInfo($message);

            foreach ($schedule->getDeviceCodes() as $deviceName) {
                try {
                    $device = $this->deviceProvider->getDevice($deviceName);
                    $device->checkStatus();

                    switch ($schedule->getCommand()) {
                        case ScheduleInterface::COMMAND_START:
                            if ($device->getStatus()) {
                                $message = sprintf('Device already running: %s', $deviceName);
                                break;
                            }

                            $device->start();
                            $message = sprintf('Device started: %s', $deviceName);
                            break;
                        case ScheduleInterface::COMMAND_STOP:
                            if (!$device->getStatus()) {
                                $message = sprintf('Device already stopped: %s', $deviceName);
                                break;
                            }

                            $device->stop();
                            $message = sprintf('Device stopped: %s', $deviceName);
                            break;
                        default:
                            $message = sprintf('Unknown command: %s', $schedule->getCommand());
                            break;
                    }

                    $this->logger->logInfo($message);
                } catch (Exception $e) {
                    $this->logger->logError($e->getMessage());
                }
            }
        }
    }
}
