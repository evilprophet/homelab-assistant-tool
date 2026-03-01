<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Support;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Entity\Schedule;
use EvilStudio\HAT\Entity\Ups;
use ReflectionProperty;

trait EntityTestHelperTrait
{
    protected function setEntityId(object $entity, int $id): void
    {
        $idProperty = new ReflectionProperty($entity, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($entity, $id);
    }

    protected function createDeviceEntity(
        int $id,
        string $name = 'device-1',
        string $ip = '10.0.0.10',
        string $mac = '00:11:22:33:44:55',
        string $platform = DevicePlatform::GENERIC->value
    ): Device {
        $device = (new Device())
            ->setName($name)
            ->setIp($ip)
            ->setMac($mac)
            ->setPlatform($platform);

        $this->setEntityId($device, $id);

        return $device;
    }

    protected function createUpsEntity(
        int $id,
        string $name = 'UPS Main',
        string $identifier = 'ups-main',
        string $host = 'ups.local'
    ): Ups {
        $ups = (new Ups())
            ->setName($name)
            ->setIdentifier($identifier)
            ->setHost($host);

        $this->setEntityId($ups, $id);

        return $ups;
    }

    protected function createScheduleEntity(
        int $id,
        string $name = 'Night Start',
        string $cronExpression = '0 2 * * *',
        string $command = ScheduleInterface::COMMAND_START
    ): Schedule {
        $schedule = (new Schedule())
            ->setName($name)
            ->setCronExpression($cronExpression)
            ->setCommand($command)
            ->setIsEnabled(true);

        $this->setEntityId($schedule, $id);

        return $schedule;
    }
}
