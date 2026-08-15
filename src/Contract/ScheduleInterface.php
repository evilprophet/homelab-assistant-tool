<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Contract;

interface ScheduleInterface
{
    public const string COMMAND_START = 'start';
    public const string COMMAND_STOP = 'stop';
    public const array COMMANDS = [
        self::COMMAND_START,
        self::COMMAND_STOP,
    ];

    public function getId(): ?int;

    public function getName(): string;

    public function isEnabled(): bool;

    public function getCronExpression(): string;

    public function getCommand(): string;

    public function toArray(): array;
}
