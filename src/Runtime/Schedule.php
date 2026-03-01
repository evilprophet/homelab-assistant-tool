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
        $devices = empty($this->devices) ? '-' : implode(', ', $this->devices);

        return [
            'id' => $this->id ?? '-',
            'name' => $this->getName(),
            'enabled' => $this->isEnabled() ? 'yes' : 'no',
            'cron_expression' => $this->getCronExpression(),
            'command' => $this->getCommand(),
            'devices' => $devices,
        ];
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
