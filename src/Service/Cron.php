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

                if ($ups->isBatteryRuntimeLow()) {
                    $device->stop();
                    $this->logger->logInfo(
                        sprintf("Device '%s' stopped - UPS '%s' has low battery.", $device->getName(), $upsIdentifier)
                    );

                    continue;
                }

                if (
                    $device->getUpsLowBatteryRuntimeThreshold()
                    && $device->getUpsLowBatteryRuntimeThreshold() > $ups->getBatteryRuntime()
                ) {
                    $device->stop();
                    $this->logger->logInfo(
                        sprintf(
                            "Device '%s' stopped - UPS '%s' has too low battery for this device.",
                            $device->getName(),
                            $upsIdentifier
                        )
                    );

                    continue;
                }

                $this->logger->logInfo(
                    sprintf(
                        "Device '%s' is running - UPS '%s' has remaining runtime: %s minutes.",
                        $device->getName(),
                        $upsIdentifier,
                        round($ups->getBatteryRuntime() / 60)
                    )
                );
            } catch (Exception $e) {
                $this->logger->logError(
                    sprintf("Error processing device '%s': %s.", $device->getName(), $e->getMessage())
                );
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

            $message = sprintf('Schedule "%s" is matching.', $schedule->getName());
            $this->logger->logInfo($message);

            switch ($schedule->getCommand()) {
                case ScheduleInterface::COMMAND_START:
                    $this->commandStart($schedule->getDeviceCodes());
                    break;
                case ScheduleInterface::COMMAND_STOP:
                    $this->commandStop($schedule->getDeviceCodes());
                    break;
                default:
                    $this->logger->logInfo(sprintf('Unknown command: %s.', $schedule->getCommand()));
                    break;
            }
        }
    }

    protected function commandStart(array $deviceCodes): void
    {
        foreach ($deviceCodes as $deviceName) {
            try {
                $device = $this->deviceProvider->getDevice($deviceName);
                $device->checkStatus();

                if ($device->getStatus()) {
                    $this->logger->logInfo(sprintf("Device '%s' already running.", $deviceName));
                    continue;
                }

                $ups = $this->upsProvider->getUps($device->getUpsIdentifier());
                if (
                    $ups->getSafeBatteryRuntimeThreshold()
                    && $ups->getSafeBatteryRuntimeThreshold() > $ups->getBatteryRuntime()
                ) {
                    $this->logger->logInfo(
                        sprintf(
                            "Device '%s' cannot be started - UPS %s has too low battery.",
                            $deviceName,
                            $device->getUpsIdentifier()
                        )
                    );
                    continue;
                }

                $device->start();
                $this->logger->logInfo(sprintf("Device '%s' started.", $deviceName));
            } catch (Exception $e) {
                $this->logger->logError($e->getMessage());
            }
        }
    }

    protected function commandStop(array $deviceCodes): void
    {
        foreach ($deviceCodes as $deviceName) {
            try {
                $device = $this->deviceProvider->getDevice($deviceName);
                $device->checkStatus();

                if (!$device->getStatus()) {
                    $this->logger->logInfo(sprintf("Device '%s' already stopped.", $deviceName));
                    continue;
                }

                $device->stop();
                $this->logger->logInfo(sprintf("Device '%s' stopped.", $deviceName));
            } catch (Exception $e) {
                $this->logger->logError($e->getMessage());
            }
        }
    }
}
