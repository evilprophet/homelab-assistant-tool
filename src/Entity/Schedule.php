<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use EvilStudio\HAT\Contract\ScheduleInterface;
use InvalidArgumentException;

#[ORM\Entity]
#[ORM\Table(name: 'schedules')]
class Schedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    protected string $name;

    #[ORM\Column(name: 'is_enabled', type: Types::BOOLEAN, options: ['default' => true])]
    protected bool $isEnabled = true;

    #[ORM\Column(name: 'cron_expression', length: 100)]
    protected string $cronExpression;

    #[ORM\Column(length: 32)]
    protected string $command;

    /** @var Collection<int, Device> */
    #[ORM\ManyToMany(targetEntity: Device::class, inversedBy: 'schedules')]
    #[ORM\JoinTable(name: 'schedule_device')]
    #[ORM\JoinColumn(name: 'schedule_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'device_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    protected Collection $devices;

    public function __construct()
    {
        $this->devices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    public function setCronExpression(string $cronExpression): self
    {
        $this->cronExpression = $cronExpression;

        return $this;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function setCommand(string $command): self
    {
        if (!in_array($command, ScheduleInterface::COMMANDS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    "Invalid schedule command '%s'. Allowed values: %s.",
                    $command,
                    implode(', ', ScheduleInterface::COMMANDS)
                )
            );
        }

        $this->command = $command;

        return $this;
    }

    public function getDevices(): Collection
    {
        return $this->devices;
    }

    public function addDevice(Device $device): self
    {
        if (!$this->devices->contains($device)) {
            $this->devices->add($device);
            $device->addSchedule($this);
        }

        return $this;
    }

    public function removeDevice(Device $device): self
    {
        if ($this->devices->removeElement($device)) {
            $device->removeSchedule($this);
        }

        return $this;
    }
}
