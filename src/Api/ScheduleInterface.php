<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Api;

use DateTime;

interface ScheduleInterface
{
    public const string COMMAND_START = 'start';
    public const string COMMAND_STOP = 'stop';

    public function getName(): string;

    public function getSchedule(): string;

    public function getCommand(): string;

    public function getDeviceCodes(): array;

    public function isCronScheduleMatching(): ?bool;

    public function checkCronSchedule(DateTime $dateTime): void;
}
