<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Factory;

use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Entity\Schedule as ScheduleEntity;
use EvilStudio\HAT\Runtime\Schedule as RuntimeSchedule;

class RuntimeScheduleFactory
{
    public function createFromEntity(ScheduleEntity $schedule): RuntimeSchedule
    {
        $deviceLabels = [];
        foreach ($schedule->getDevices()->toArray() as $device) {
            if (!$device instanceof Device || $device->getId() === null) {
                continue;
            }

            $deviceLabels[] = sprintf('%d:%s', $device->getId(), $device->getName());
        }

        return new RuntimeSchedule(
            $schedule->getId(),
            $schedule->getName(),
            $schedule->isEnabled(),
            $schedule->getCronExpression(),
            $schedule->getCommand(),
            $deviceLabels
        );
    }
}
