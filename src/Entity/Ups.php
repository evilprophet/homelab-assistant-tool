<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ups')]
class Ups
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 255)]
    protected string $name;

    #[ORM\Column(length: 255, unique: true)]
    protected string $identifier;

    #[ORM\Column(length: 255)]
    protected string $host;

    #[ORM\Column(
        name: 'safe_battery_runtime_threshold',
        type: Types::INTEGER,
        nullable: true,
        options: ['unsigned' => true]
    )]
    protected ?int $safeBatteryRuntimeThreshold = null;

    /** @var Collection<int, Device> */
    #[ORM\OneToMany(targetEntity: Device::class, mappedBy: 'ups')]
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

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getSafeBatteryRuntimeThreshold(): ?int
    {
        return $this->safeBatteryRuntimeThreshold;
    }

    public function setSafeBatteryRuntimeThreshold(?int $safeBatteryRuntimeThreshold): self
    {
        $this->safeBatteryRuntimeThreshold = $safeBatteryRuntimeThreshold;

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
            $device->setUps($this);
        }

        return $this;
    }

    public function removeDevice(Device $device): self
    {
        if ($this->devices->removeElement($device) && $device->getUps() === $this) {
            $device->setUps(null);
        }

        return $this;
    }
}
