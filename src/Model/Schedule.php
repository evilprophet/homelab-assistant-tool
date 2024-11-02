<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Model;

use Cron\CronExpression;
use DateTime;
use EvilStudio\HAT\Api\ScheduleInterface;

class Schedule implements ScheduleInterface
{
    protected ?bool $isCronScheduleMatching = null;

    public function __construct(
        protected string $name,
        protected string $schedule,
        protected string $command,
        protected array $deviceCodes,
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSchedule(): string
    {
        return $this->schedule;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getDeviceCodes(): array
    {
        return $this->deviceCodes;
    }

    public function isCronScheduleMatching(): ?bool
    {
        return $this->isCronScheduleMatching;
    }

    public function checkCronSchedule(DateTime $dateTime): void
    {
        $cron = new CronExpression($this->getSchedule());

        $dateTimeBefore = (clone $dateTime)->modify('-1 minute');
        $dateTimeAfter = (clone $dateTime)->modify('+1 minute');

        $this->isCronScheduleMatching = $cron->isDue($dateTimeBefore) || $cron->isDue($dateTime) || $cron->isDue($dateTimeAfter);
    }
}
