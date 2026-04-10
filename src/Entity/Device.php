<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'devices',
    indexes: [
        new ORM\Index(name: 'idx_devices_ups_id', columns: ['ups_id']),
    ]
)]
class Device
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    protected string $name;

    #[ORM\Column(length: 45)]
    protected string $ip;

    #[ORM\Column(length: 17)]
    protected string $mac;

    #[ORM\Column(length: 64)]
    protected string $platform;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $username = null;

    #[ORM\Column(
        name: 'ups_low_battery_runtime_threshold',
        type: Types::INTEGER,
        nullable: true,
        options: ['unsigned' => true]
    )]
    protected ?int $upsLowBatteryRuntimeThreshold = null;

    #[ORM\Column(name: 'auto_stop_allowed', type: Types::BOOLEAN, options: ['default' => true])]
    protected bool $autoStopAllowed = true;

    #[ORM\ManyToOne(targetEntity: Ups::class, inversedBy: 'devices')]
    #[ORM\JoinColumn(name: 'ups_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    protected ?Ups $ups = null;

    /** @var Collection<int, Schedule> */
    #[ORM\ManyToMany(targetEntity: Schedule::class, mappedBy: 'devices')]
    protected Collection $schedules;

    public function __construct()
    {
        $this->schedules = new ArrayCollection();
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

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getMac(): string
    {
        return $this->mac;
    }

    public function setMac(string $mac): self
    {
        $this->mac = str_replace('-', ':', mb_strtoupper(trim($mac)));

        return $this;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): self
    {
        $this->platform = $platform;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getUpsLowBatteryRuntimeThreshold(): ?int
    {
        return $this->upsLowBatteryRuntimeThreshold;
    }

    public function setUpsLowBatteryRuntimeThreshold(?int $upsLowBatteryRuntimeThreshold): self
    {
        $this->upsLowBatteryRuntimeThreshold = $upsLowBatteryRuntimeThreshold;

        return $this;
    }

    public function isAutoStopAllowed(): bool
    {
        return $this->autoStopAllowed;
    }

    public function setAutoStopAllowed(bool $autoStopAllowed): self
    {
        $this->autoStopAllowed = $autoStopAllowed;

        return $this;
    }

    public function getUps(): ?Ups
    {
        return $this->ups;
    }

    public function setUps(?Ups $ups): self
    {
        if ($this->ups === $ups) {
            return $this;
        }

        $previousUps = $this->ups;
        if ($previousUps !== null) {
            $previousUps->getDevices()->removeElement($this);
        }

        $this->ups = $ups;

        if ($ups !== null && !$ups->getDevices()->contains($this)) {
            $ups->getDevices()->add($this);
        }

        return $this;
    }

    public function getSchedules(): Collection
    {
        return $this->schedules;
    }

    public function addSchedule(Schedule $schedule): self
    {
        if (!$this->schedules->contains($schedule)) {
            $this->schedules->add($schedule);
            $schedule->addDevice($this);
        }

        return $this;
    }

    public function removeSchedule(Schedule $schedule): self
    {
        if ($this->schedules->removeElement($schedule)) {
            $schedule->removeDevice($this);
        }

        return $this;
    }
}
