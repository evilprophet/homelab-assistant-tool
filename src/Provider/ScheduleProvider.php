<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Provider;

use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Schedule;

class ScheduleProvider
{
    protected array $scheduleList = [];

    public function __construct(
        protected Configuration $configuration,
        array $schedulesData
    )
    {
        if (empty($schedulesData)) {
            return;
        }

        $currentDateTime = $this->configuration->getCurrentDateTime();

        foreach ($schedulesData as $scheduleData) {
            $schedule = new Schedule(
                $scheduleData['name'],
                $scheduleData['schedule'],
                $scheduleData['command'],
                $scheduleData['devices'],
            );

            $schedule->checkCronSchedule($currentDateTime);

            $this->scheduleList[] = $schedule;
        }
    }

    public function getSchedules(): array
    {
        return $this->scheduleList;
    }
}
