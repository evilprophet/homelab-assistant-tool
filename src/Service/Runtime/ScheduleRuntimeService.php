<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Runtime;

use EvilStudio\HAT\Factory\RuntimeScheduleFactory;
use EvilStudio\HAT\Service\Application\ScheduleService;

class ScheduleRuntimeService
{
    public function __construct(
        protected ScheduleService $scheduleService,
        protected RuntimeScheduleFactory $runtimeScheduleFactory
    ) {
    }

    public function listRuntimeSchedules(): array
    {
        $runtimeSchedules = [];
        foreach ($this->scheduleService->listSchedules() as $scheduleEntity) {
            $runtimeSchedules[] = $this->runtimeScheduleFactory->createFromEntity($scheduleEntity);
        }

        return $runtimeSchedules;
    }
}
