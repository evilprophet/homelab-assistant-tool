<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Runtime;

use Cron\CronExpression;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Contract\DeviceAction;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Exception\UnsupportedDeviceAction;
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
        $upsByIdentifier = $this->upsRuntimeService->pollAllUpsStatus();

        foreach ($upsByIdentifier as $identifier => $ups) {
            if ($ups === null) {
                $this->logError(
                    sprintf("UPS '%s' status is unavailable - treated as unknown for this run.", $identifier)
                );
            }
        }

        if ($this->configuration->isUpsModeEnabled() && $this->isAnyUpsOnBattery($upsByIdentifier)) {
            $this->handleBatteryMode($upsByIdentifier);

            return;
        }

        $this->handleOnlineMode($upsByIdentifier);
    }

    protected function isAnyUpsOnBattery(array $upsByIdentifier): bool
    {
        foreach ($upsByIdentifier as $ups) {
            if ($ups !== null && $ups->isOnBattery()) {
                return true;
            }
        }

        return false;
    }

    protected function handleBatteryMode(array $upsByIdentifier): void
    {
        $this->logWarning('[UPS Battery Mode enabled]');

        $deviceList = array_values(array_filter(
            $this->deviceOperationsService->listDevices(true),
            static fn (DeviceInterface $device): bool => $device->isAutoStopAllowed()
        ));

        foreach ($deviceList as $device) {
            if (!$device->getStatus()) {
                continue;
            }

            $deviceName = $device->getName();
            $upsIdentifier = $device->getUpsIdentifier();
            if (empty($upsIdentifier)) {
                continue;
            }

            $ups = $upsByIdentifier[$upsIdentifier] ?? null;
            if ($ups === null) {
                $this->logWarning(
                    sprintf(
                        "Device '%s' left running - UPS '%s' status is unknown.",
                        $deviceName,
                        $upsIdentifier
                    )
                );

                continue;
            }

            if (!$ups->isOnBattery()) {
                $this->logInfo(
                    sprintf("Device '%s' left running - UPS '%s' is on mains power.", $deviceName, $upsIdentifier)
                );

                continue;
            }

            try {
                $batteryRuntime = $ups->getBatteryRuntime();

                if ($ups->isBatteryRuntimeLow()) {
                    $this->deviceOperationsService->assertDeviceActionSupported($device, DeviceAction::STOP);
                    $this->logStopResult(
                        $device->stop(),
                        sprintf("Device '%s' stopped - UPS '%s' has low battery.", $deviceName, $upsIdentifier),
                        sprintf(
                            "Device '%s' FAILED to stop - UPS '%s' has low battery.",
                            $deviceName,
                            $upsIdentifier
                        )
                    );

                    continue;
                }

                $deviceRuntimeThreshold = $device->getUpsLowBatteryRuntimeThreshold();
                if ($deviceRuntimeThreshold > 0) {
                    if ($batteryRuntime === null) {
                        $this->logWarning(
                            sprintf(
                                "Device '%s' threshold check skipped - UPS '%s' runtime is unavailable.",
                                $deviceName,
                                $upsIdentifier
                            )
                        );
                    } elseif ($deviceRuntimeThreshold > $batteryRuntime) {
                        $this->deviceOperationsService->assertDeviceActionSupported($device, DeviceAction::STOP);
                        $this->logStopResult(
                            $device->stop(),
                            sprintf(
                                "Device '%s' stopped - UPS '%s' has too low battery for this device.",
                                $deviceName,
                                $upsIdentifier
                            ),
                            sprintf(
                                "Device '%s' FAILED to stop - UPS '%s' has too low battery for this device.",
                                $deviceName,
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
                        $deviceName,
                        $upsIdentifier,
                        $runtimeInfo
                    )
                );
            } catch (UnsupportedDeviceAction $e) {
                $this->logInfo(
                    sprintf("Device '%s' action skipped: %s", $deviceName, $e->getMessage())
                );
            } catch (Exception $e) {
                $this->logError(
                    sprintf("Error processing device '%s': %s.", $deviceName, $e->getMessage())
                );
            }
        }
    }

    protected function handleOnlineMode(array $upsByIdentifier): void
    {
        $schedules = $this->scheduleService->listEnabledSchedules();

        foreach ($schedules as $schedule) {
            try {
                $isDue = $this->isScheduleDue($schedule->getCronExpression());
            } catch (Exception $e) {
                // A single unparsable row must not starve every other schedule,
                // which is what an exception escaping this loop would do.
                $this->logWarning(
                    sprintf(
                        'Schedule "%s" skipped - invalid cron expression "%s": %s',
                        $schedule->getName(),
                        $schedule->getCronExpression(),
                        $e->getMessage()
                    )
                );
                continue;
            }

            if (!$isDue) {
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
                    $this->commandStart($deviceNames, $upsByIdentifier);
                    break;
                case ScheduleInterface::COMMAND_STOP:
                    $this->commandStop($deviceNames);
                    break;
                default:
                    $this->logWarning(sprintf('Unknown command: %s.', $schedule->getCommand()));
                    break;
            }
        }
    }

    protected function commandStart(array $deviceCodes, array $upsByIdentifier): void
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
                    $ups = $upsByIdentifier[$device->getUpsIdentifier()] ?? null;
                    if ($ups === null) {
                        $this->logInfo(
                            sprintf(
                                "Device '%s' cannot be started - UPS %s status is unknown.",
                                $deviceName,
                                $device->getUpsIdentifier()
                            )
                        );
                        continue;
                    }

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

                $this->deviceOperationsService->assertDeviceActionSupported($device, DeviceAction::START);
                $device->start();
                $this->logInfo(sprintf("Wake-on-LAN packet sent to device '%s'.", $deviceName));
            } catch (UnsupportedDeviceAction $e) {
                $this->logInfo(
                    sprintf("Device '%s' action skipped: %s", $deviceName, $e->getMessage())
                );
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

                if (!$device->isAutoStopAllowed()) {
                    $this->logInfo(sprintf("Device '%s' action skipped: auto-stop is disabled.", $deviceName));
                    continue;
                }

                $this->deviceOperationsService->assertDeviceActionSupported($device, DeviceAction::STOP);
                $this->logStopResult(
                    $device->stop(),
                    sprintf("Device '%s' stopped.", $deviceName),
                    sprintf("Device '%s' FAILED to stop.", $deviceName)
                );
            } catch (UnsupportedDeviceAction $e) {
                $this->logInfo(
                    sprintf("Device '%s' action skipped: %s", $deviceName, $e->getMessage())
                );
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

    protected function logStopResult(bool $stopped, string $successMessage, string $failureMessage): void
    {
        if ($stopped) {
            $this->logInfo($successMessage);

            return;
        }

        $this->logError($failureMessage);
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
