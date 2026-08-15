<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Application;

use Cron\CronExpression;
use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Entity\Schedule;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\DeviceRepository;
use EvilStudio\HAT\Repository\ScheduleRepository;
use InvalidArgumentException;

class ScheduleService extends AbstractDatabaseService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        protected ScheduleRepository $scheduleRepository,
        protected DeviceRepository $deviceRepository
    ) {
        parent::__construct($entityManager);
    }

    public function listSchedules(): array
    {
        return $this->scheduleRepository->findAll();
    }

    public function listEnabledSchedules(): array
    {
        return $this->scheduleRepository->findAllEnabled();
    }

    public function getScheduleById(int $scheduleId): Schedule
    {
        $schedule = $this->scheduleRepository->findById($scheduleId);
        if ($schedule === null) {
            throw EntityNotFound::forField('Schedule', 'id', $scheduleId);
        }

        return $schedule;
    }

    public function createSchedule(
        string $name,
        string $cronExpression,
        string $command,
        array $deviceIds,
        bool $isEnabled = true
    ): Schedule {
        $this->assertCronExpressionIsValid($cronExpression);
        $this->assertCommandIsSupported($command);
        $this->ensureNameIsUnique($name);
        $devices = $this->resolveDevicesByIds($deviceIds);

        $schedule = new Schedule();
        $schedule
            ->setName($name)
            ->setIsEnabled($isEnabled)
            ->setCronExpression($cronExpression)
            ->setCommand($command);

        foreach ($devices as $device) {
            $schedule->addDevice($device);
        }

        $this->persist($schedule);
        $this->flushExpectingUnique('Schedule', 'name', $name);

        return $schedule;
    }

    public function updateSchedule(
        int $scheduleId,
        string $name,
        bool $isEnabled,
        string $cronExpression,
        string $command,
        array $deviceIds
    ): Schedule {
        $this->assertCronExpressionIsValid($cronExpression);
        $this->assertCommandIsSupported($command);
        $schedule = $this->getScheduleById($scheduleId);
        $this->ensureNameIsUnique($name, $scheduleId);
        $devices = $this->resolveDevicesByIds($deviceIds);

        foreach ($schedule->getDevices()->toArray() as $device) {
            if ($device instanceof Device) {
                $schedule->removeDevice($device);
            }
        }

        foreach ($devices as $device) {
            $schedule->addDevice($device);
        }

        $schedule
            ->setName($name)
            ->setIsEnabled($isEnabled)
            ->setCronExpression($cronExpression)
            ->setCommand($command);

        $this->flushExpectingUnique('Schedule', 'name', $name);

        return $schedule;
    }

    public function removeSchedule(int $scheduleId): void
    {
        $schedule = $this->getScheduleById($scheduleId);

        $this->remove($schedule);
        $this->flush();
    }

    protected function assertCronExpressionIsValid(string $cronExpression): void
    {
        if (CronExpression::isValidExpression($cronExpression)) {
            return;
        }

        throw new InvalidArgumentException(sprintf("Invalid cron expression '%s'.", $cronExpression));
    }

    protected function assertCommandIsSupported(string $command): void
    {
        if (in_array($command, ScheduleInterface::COMMANDS, true)) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                "Unsupported schedule command '%s'. Allowed values: %s.",
                $command,
                implode(', ', ScheduleInterface::COMMANDS)
            )
        );
    }

    protected function ensureNameIsUnique(string $name, ?int $excludeScheduleId = null): void
    {
        $existingSchedule = $this->scheduleRepository->findOneByName($name);
        if ($existingSchedule === null) {
            return;
        }

        if ($excludeScheduleId !== null && $existingSchedule->getId() === $excludeScheduleId) {
            return;
        }

        throw EntityAlreadyExists::forField('Schedule', 'name', $name);
    }

    protected function resolveDevicesByIds(array $deviceIds): array
    {
        $normalizedDeviceIds = array_values(array_unique($deviceIds));

        if (empty($normalizedDeviceIds)) {
            return [];
        }

        $devices = $this->deviceRepository->findByIds($normalizedDeviceIds);
        $devicesById = [];
        foreach ($devices as $device) {
            $resolvedDeviceId = $device->getId();
            if ($resolvedDeviceId === null) {
                continue;
            }

            $devicesById[$resolvedDeviceId] = $device;
        }

        $resolvedDevices = [];
        foreach ($normalizedDeviceIds as $deviceId) {
            $resolvedDevice = $devicesById[$deviceId] ?? null;
            if ($resolvedDevice === null) {
                throw EntityNotFound::forField('Device', 'id', $deviceId);
            }

            $resolvedDevices[] = $resolvedDevice;
        }

        return $resolvedDevices;
    }
}
