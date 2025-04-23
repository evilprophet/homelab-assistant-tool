<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Provider;

use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Schedule;

class ScheduleProvider extends AbstractProvider
{
    protected array $properties = ['Name', 'Schedule', 'Command', 'Devices'];
    protected array $scheduleList = [];

    public function __construct(
        Configuration $configuration,
        array $schedulesData
    ) {
        parent::__construct($configuration);

        if (empty($schedulesData)) {
            return;
        }

        foreach ($schedulesData as $scheduleData) {
            $schedule = new Schedule(
                $scheduleData['name'],
                $scheduleData['schedule'],
                $scheduleData['command'],
                $scheduleData['devices'],
            );

            $this->scheduleList[] = $schedule;
        }
    }

    public function getScheduleList(): array
    {
        return $this->scheduleList;
    }

    public function checkAllCronSchedule(): void
    {
        $currentDateTime = $this->configuration->getCurrentDateTime();

        foreach ($this->getScheduleList() as $schedule) {
            $schedule->checkCronSchedule($currentDateTime);
        }
    }
}
