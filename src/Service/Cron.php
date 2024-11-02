<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service;

use EvilStudio\HAT\Api\ScheduleInterface;
use EvilStudio\HAT\Provider\DeviceProvider;
use EvilStudio\HAT\Provider\ScheduleProvider;
use Exception;

class Cron
{
    public function __construct(
        protected DeviceProvider $deviceProvider,
        protected ScheduleProvider $scheduleProvider,
        protected Logger $logger
    )
    {
    }

    public function execute(): void
    {
        $this->logger->logInfo('Cron job started');

        $schedules = $this->scheduleProvider->getSchedules();

        foreach ($schedules as $schedule) {
            if (!$schedule->isCronScheduleMatching()) {
                continue;
            }

            $message = sprintf('Schedule "%s" is matching', $schedule->getName());
            $this->logger->logInfo($message);

            foreach ($schedule->getDeviceCodes() as $deviceCode) {
                try {
                    $device = $this->deviceProvider->getDevice($deviceCode);

                    switch ($schedule->getCommand()) {
                        case ScheduleInterface::COMMAND_START:
                            $device->start();
                            $message = sprintf('Device started: %s', $deviceCode);
                            break;
                        case ScheduleInterface::COMMAND_STOP:
                            $device->stop();
                            $message = sprintf('Device stopped: %s', $deviceCode);
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

        $this->logger->logInfo('Cron job finished');
    }
}
