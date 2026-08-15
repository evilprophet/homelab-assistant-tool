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
    public const string MAC_PATTERN = '/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/i';
    // Must not start with '-': the name is glued into an argv token for ssh,
    // where a leading dash is parsed as an option instead of a login.
    public const string USERNAME_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9._-]{0,63}$/';

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
        $normalizedName = $this->normalizeName($name);
        $normalizedIp = $this->normalizeIp($ip);
        $normalizedMac = $this->normalizeMac($mac);
        $normalizedUsername = $this->normalizeUsername($username);
        $this->assertThresholdIsNonNegative($upsLowBatteryRuntimeThreshold);
        $this->ensureNameIsUnique($normalizedName);
        $this->assertSupportedPlatform($platform);

        $device = new Device();
        $device
            ->setName($normalizedName)
            ->setIp($normalizedIp)
            ->setMac($normalizedMac)
            ->setPlatform($platform)
            ->setUsername($normalizedUsername)
            ->setUpsLowBatteryRuntimeThreshold($upsLowBatteryRuntimeThreshold)
            ->setAutoStopAllowed($autoStopAllowed)
            ->setUps($this->resolveUps($upsId));

        $this->persist($device);
        $this->flushExpectingUnique('Device', 'name', $normalizedName);

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
        $normalizedName = $this->normalizeName($name);
        $normalizedIp = $this->normalizeIp($ip);
        $normalizedMac = $this->normalizeMac($mac);
        $normalizedUsername = $this->normalizeUsername($username);
        $this->assertThresholdIsNonNegative($upsLowBatteryRuntimeThreshold);
        $device = $this->getDeviceById($deviceId);
        $this->ensureNameIsUnique($normalizedName, $deviceId);
        $this->assertSupportedPlatform($platform);
        // Resolved up front: throwing mid-chain would leave the managed entity
        // partially updated, and flush() is EntityManager-wide.
        $ups = $this->resolveUps($upsId);

        $device
            ->setName($normalizedName)
            ->setIp($normalizedIp)
            ->setMac($normalizedMac)
            ->setPlatform($platform)
            ->setUsername($normalizedUsername)
            ->setUpsLowBatteryRuntimeThreshold($upsLowBatteryRuntimeThreshold)
            ->setAutoStopAllowed($autoStopAllowed ?? $device->isAutoStopAllowed())
            ->setUps($ups);

        $this->flushExpectingUnique('Device', 'name', $normalizedName);

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

    protected function normalizeName(string $name): string
    {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw new InvalidArgumentException('Device name cannot be empty.');
        }

        return $normalizedName;
    }

    protected function normalizeUsername(?string $username): ?string
    {
        $normalizedUsername = trim((string)$username);
        if ($normalizedUsername === '') {
            return null;
        }

        if (preg_match(self::USERNAME_PATTERN, $normalizedUsername) !== 1) {
            throw new InvalidArgumentException(sprintf(
                "'%s' is not a valid SSH username (letters, digits, dot, underscore and dash; "
                . 'cannot start with a dash).',
                $username
            ));
        }

        return $normalizedUsername;
    }

    protected function assertThresholdIsNonNegative(?int $threshold): void
    {
        if ($threshold !== null && $threshold < 0) {
            throw new InvalidArgumentException('UPS low battery runtime threshold cannot be negative.');
        }
    }

    protected function normalizeIp(string $ip): string
    {
        $normalizedIp = trim($ip);
        if (filter_var($normalizedIp, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException(sprintf("'%s' is not a valid IP address.", $ip));
        }

        return $normalizedIp;
    }

    protected function normalizeMac(string $mac): string
    {
        $normalizedMac = trim($mac);
        if (preg_match(self::MAC_PATTERN, $normalizedMac) !== 1) {
            throw new InvalidArgumentException(
                sprintf("'%s' is not a valid MAC address (format: 00:11:22:33:44:55).", $mac)
            );
        }

        return $normalizedMac;
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
