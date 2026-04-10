<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Application;

use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Entity\Schedule;
use EvilStudio\HAT\Entity\Ups;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\DeviceRepository;
use EvilStudio\HAT\Repository\UpsRepository;
use InvalidArgumentException;

class DeviceService extends AbstractDatabaseService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        protected DeviceRepository $deviceRepository,
        protected UpsRepository $upsRepository
    ) {
        parent::__construct($entityManager);
    }

    public function listDevices(): array
    {
        return $this->deviceRepository->findAll();
    }

    public function getDeviceById(int $deviceId): Device
    {
        $device = $this->deviceRepository->findById($deviceId);
        if ($device === null) {
            throw EntityNotFound::forField('Device', 'id', $deviceId);
        }

        return $device;
    }

    public function getDeviceByName(string $deviceName): Device
    {
        $device = $this->deviceRepository->findOneByName($deviceName);
        if ($device === null) {
            throw EntityNotFound::forField('Device', 'name', $deviceName);
        }

        return $device;
    }

    public function createDevice(
        string $name,
        string $ip,
        string $mac,
        string $platform,
        ?string $username = null,
        ?int $upsLowBatteryRuntimeThreshold = null,
        ?int $upsId = null,
        bool $autoStopAllowed = true
    ): Device {
        $this->ensureNameIsUnique($name);
        $this->assertSupportedPlatform($platform);

        $device = new Device();
        $device
            ->setName($name)
            ->setIp($ip)
            ->setMac($mac)
            ->setPlatform($platform)
            ->setUsername($username)
            ->setUpsLowBatteryRuntimeThreshold($upsLowBatteryRuntimeThreshold)
            ->setAutoStopAllowed($autoStopAllowed)
            ->setUps($this->resolveUps($upsId));

        $this->persist($device);
        $this->flush();

        return $device;
    }

    public function updateDevice(
        int $deviceId,
        string $name,
        string $ip,
        string $mac,
        string $platform,
        ?string $username = null,
        ?int $upsLowBatteryRuntimeThreshold = null,
        ?int $upsId = null,
        ?bool $autoStopAllowed = null
    ): Device {
        $device = $this->getDeviceById($deviceId);
        $this->ensureNameIsUnique($name, $deviceId);
        $this->assertSupportedPlatform($platform);

        $device
            ->setName($name)
            ->setIp($ip)
            ->setMac($mac)
            ->setPlatform($platform)
            ->setUsername($username)
            ->setUpsLowBatteryRuntimeThreshold($upsLowBatteryRuntimeThreshold)
            ->setAutoStopAllowed($autoStopAllowed ?? $device->isAutoStopAllowed())
            ->setUps($this->resolveUps($upsId));

        $this->flush();

        return $device;
    }

    public function removeDevice(int $deviceId): void
    {
        $device = $this->getDeviceById($deviceId);

        $this->runInTransaction(function () use ($device): void {
            foreach ($device->getSchedules()->toArray() as $schedule) {
                if ($schedule instanceof Schedule) {
                    $device->removeSchedule($schedule);
                }
            }

            $this->remove($device);
        });
    }

    protected function ensureNameIsUnique(string $name, ?int $excludeDeviceId = null): void
    {
        $existingDevice = $this->deviceRepository->findOneByName($name);
        if ($existingDevice === null) {
            return;
        }

        if ($excludeDeviceId !== null && $existingDevice->getId() === $excludeDeviceId) {
            return;
        }

        throw EntityAlreadyExists::forField('Device', 'name', $name);
    }

    protected function resolveUps(?int $upsId): ?Ups
    {
        if ($upsId === null) {
            return null;
        }

        $ups = $this->upsRepository->findById($upsId);
        if ($ups === null) {
            throw EntityNotFound::forField('UPS', 'id', $upsId);
        }

        return $ups;
    }

    protected function assertSupportedPlatform(string $platform): void
    {
        if (DevicePlatform::tryFrom($platform) !== null) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                "Unsupported platform '%s'. Allowed values: %s.",
                $platform,
                implode(', ', DevicePlatform::values())
            )
        );
    }
}
