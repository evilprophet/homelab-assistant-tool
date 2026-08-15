<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Runtime;

use EvilStudio\HAT\Contract\ScheduleInterface;

class Schedule implements ScheduleInterface
{
    public function __construct(
        protected ?int $id,
        protected string $name,
        protected bool $isEnabled,
        protected string $cronExpression,
        protected string $command,
        protected array $devices
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ?? '-',
            'name' => $this->getName(),
            'enabled' => $this->isEnabled() ? 'yes' : 'no',
            'cron_expression' => $this->getCronExpression(),
            'command' => $this->getCommand(),
            // Structured, because joining names into one string loses the id link and
            // breaks apart again on any name containing the delimiter.
            'devices' => $this->devices,
        ];
    }

    public function getDevices(): array
    {
        return $this->devices;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    public function getCommand(): string
    {
        return $this->command;
    }
}
