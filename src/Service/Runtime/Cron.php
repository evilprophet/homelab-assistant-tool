<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Runtime;

use Cron\CronExpression;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use Exception;

class Cron
{
    public function __construct(
        protected DeviceOperationsService $deviceOperationsService,
        protected UpsRuntimeService $upsRuntimeService,
        protected ScheduleService $scheduleService,
        protected Configuration $configuration,
        protected ActionLogService $actionLogService
    ) {
    }

    public function execute(): void
    {
        $this->upsRuntimeService->updateAllUpsStatus();

        if ($this->configuration->isUpsModeEnabled() && $this->upsRuntimeService->isAnyUpsOnBattery()) {
            $this->handleBatteryMode();

            return;
        }

        $this->handleOnlineMode();
    }

    protected function handleBatteryMode(): void
    {
        $this->logWarning('[UPS Battery Mode enabled]');

        $deviceList = $this->deviceOperationsService->listDevices(true);

        foreach ($deviceList as $device) {
            if (!$device->getStatus()) {
                continue;
            }

            $upsIdentifier = $device->getUpsIdentifier();
            if (empty($upsIdentifier)) {
                continue;
            }

            try {
                $ups = $this->upsRuntimeService->getRuntimeUpsByIdentifier($upsIdentifier);
                $ups->updateStatus();
                $batteryRuntime = $ups->getBatteryRuntime();

                if ($ups->isBatteryRuntimeLow()) {
                    $device->stop();
                    $this->logInfo(
                        sprintf("Device '%s' stopped - UPS '%s' has low battery.", $device->getName(), $upsIdentifier)
                    );

                    continue;
                }

                $deviceRuntimeThreshold = $device->getUpsLowBatteryRuntimeThreshold();
                if ($deviceRuntimeThreshold > 0) {
                    if ($batteryRuntime === null) {
                        $this->logWarning(
                            sprintf(
                                "Device '%s' threshold check skipped - UPS '%s' runtime is unavailable.",
                                $device->getName(),
                                $upsIdentifier
                            )
                        );
                    } elseif ($deviceRuntimeThreshold > $batteryRuntime) {
                        $device->stop();
                        $this->logInfo(
                            sprintf(
                                "Device '%s' stopped - UPS '%s' has too low battery for this device.",
                                $device->getName(),
                                $upsIdentifier
                            )
                        );

                        continue;
                    }
                }

                $runtimeInfo = $batteryRuntime === null ? 'unknown' : (string)round($batteryRuntime / 60);
                $this->logInfo(
                    sprintf(
                        "Device '%s' is running - UPS '%s' has remaining runtime: %s minutes.",
                        $device->getName(),
                        $upsIdentifier,
                        $runtimeInfo
                    )
                );
            } catch (Exception $e) {
                $this->logError(
                    sprintf("Error processing device '%s': %s.", $device->getName(), $e->getMessage())
                );
            }
        }
    }

    protected function handleOnlineMode(): void
    {
        $schedules = $this->scheduleService->listEnabledSchedules();

        foreach ($schedules as $schedule) {
            if (!$this->isScheduleDue($schedule->getCronExpression())) {
                continue;
            }

            $message = sprintf('Schedule "%s" is matching.', $schedule->getName());
            $this->logInfo($message);

            $deviceNames = [];
            foreach ($schedule->getDevices()->toArray() as $device) {
                if ($device instanceof Device) {
                    $deviceNames[] = $device->getName();
                }
            }

            switch ($schedule->getCommand()) {
                case ScheduleInterface::COMMAND_START:
                    $this->commandStart($deviceNames);
                    break;
                case ScheduleInterface::COMMAND_STOP:
                    $this->commandStop($deviceNames);
                    break;
                default:
                    $this->logInfo(sprintf('Unknown command: %s.', $schedule->getCommand()));
                    break;
            }
        }
    }

    protected function commandStart(array $deviceCodes): void
    {
        foreach ($deviceCodes as $deviceName) {
            try {
                $device = $this->deviceOperationsService->getDevice($deviceName);
                $device->checkStatus();

                if ($device->getStatus()) {
                    $this->logInfo(sprintf("Device '%s' already running.", $deviceName));
                    continue;
                }

                if ($device->getUpsIdentifier()) {
                    $ups = $this->upsRuntimeService->getRuntimeUpsByIdentifier($device->getUpsIdentifier());
                    $ups->updateStatus();
                    $safeBatteryRuntimeThreshold = $ups->getSafeBatteryRuntimeThreshold();
                    $batteryRuntime = $ups->getBatteryRuntime();

                    if ($safeBatteryRuntimeThreshold !== null && $batteryRuntime === null) {
                        $this->logInfo(
                            sprintf(
                                "Device '%s' cannot be started - UPS %s runtime is unavailable.",
                                $deviceName,
                                $device->getUpsIdentifier()
                            )
                        );
                        continue;
                    }

                    if ($safeBatteryRuntimeThreshold !== null && $safeBatteryRuntimeThreshold > $batteryRuntime) {
                        $this->logInfo(
                            sprintf(
                                "Device '%s' cannot be started - UPS %s has too low battery.",
                                $deviceName,
                                $device->getUpsIdentifier()
                            )
                        );
                        continue;
                    }
                }

                $device->start();
                $this->logInfo(sprintf("Device '%s' started.", $deviceName));
            } catch (Exception $e) {
                $this->logError($e->getMessage());
            }
        }
    }

    protected function commandStop(array $deviceCodes): void
    {
        foreach ($deviceCodes as $deviceName) {
            try {
                $device = $this->deviceOperationsService->getDevice($deviceName);
                $device->checkStatus();

                if (!$device->getStatus()) {
                    $this->logInfo(sprintf("Device '%s' already stopped.", $deviceName));
                    continue;
                }

                $device->stop();
                $this->logInfo(sprintf("Device '%s' stopped.", $deviceName));
            } catch (Exception $e) {
                $this->logError($e->getMessage());
            }
        }
    }

    protected function isScheduleDue(string $cronExpression): bool
    {
        $cron = new CronExpression($cronExpression);
        $currentDateTime = $this->configuration->getCurrentDateTime();

        $dateTimeBefore = (clone $currentDateTime)->modify('-1 minute');
        $dateTimeAfter = (clone $currentDateTime)->modify('+1 minute');

        return $cron->isDue($dateTimeBefore)
            || $cron->isDue($currentDateTime)
            || $cron->isDue($dateTimeAfter);
    }

    protected function logInfo(string $message): void
    {
        $this->createCronActionLog(ActionLog::LEVEL_INFO, $message);
    }

    protected function logWarning(string $message): void
    {
        $this->createCronActionLog(ActionLog::LEVEL_WARNING, $message);
    }

    protected function logError(string $message): void
    {
        $this->createCronActionLog(ActionLog::LEVEL_ERROR, $message);
    }

    protected function createCronActionLog(string $level, string $message): void
    {
        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CRON,
            ActionLogAction::CRON_EXECUTE->value,
            $level,
            $message
        );
    }
}
