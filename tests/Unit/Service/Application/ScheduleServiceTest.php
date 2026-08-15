<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Application;

use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\DeviceRepository;
use EvilStudio\HAT\Repository\ScheduleRepository;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ScheduleServiceTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testCreateScheduleRejectsInvalidCronExpression(): void
    {
        $scheduleRepository = $this->createMock(ScheduleRepository::class);
        $scheduleRepository->expects($this->never())->method('findOneByName');

        $service = new ScheduleService(
            $this->createStub(EntityManagerInterface::class),
            $scheduleRepository,
            $this->createStub(DeviceRepository::class)
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid cron expression 'not a cron'.");

        $service->createSchedule('Night Start', 'not a cron', ScheduleInterface::COMMAND_START, []);
    }

    public function testUpdateScheduleRejectsInvalidCronExpression(): void
    {
        $scheduleRepository = $this->createMock(ScheduleRepository::class);
        $scheduleRepository->expects($this->never())->method('findById');

        $service = new ScheduleService(
            $this->createStub(EntityManagerInterface::class),
            $scheduleRepository,
            $this->createStub(DeviceRepository::class)
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid cron expression');

        $service->updateSchedule(1, 'Night Start', true, '99 * * * *', ScheduleInterface::COMMAND_START, []);
    }

    public function testCreateScheduleRejectsUnsupportedCommand(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $scheduleRepository = $this->createMock(ScheduleRepository::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);

        $scheduleRepository->expects($this->never())->method('findOneByName');

        $service = new ScheduleService($entityManager, $scheduleRepository, $deviceRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported schedule command 'reboot'. Allowed values: start, stop.");

        $service->createSchedule('Night Start', '0 2 * * *', 'reboot', []);
    }

    public function testUpdateScheduleRejectsUnsupportedCommand(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $scheduleRepository = $this->createMock(ScheduleRepository::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);

        $scheduleRepository->expects($this->never())->method('findById');

        $service = new ScheduleService($entityManager, $scheduleRepository, $deviceRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported schedule command 'reboot'.");

        $service->updateSchedule(1, 'Night Start', true, '0 2 * * *', 'reboot', []);
    }

    public function testCreateScheduleThrowsWhenNameAlreadyExists(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $scheduleRepository = $this->createMock(ScheduleRepository::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);

        $scheduleRepository->expects($this->once())
            ->method('findOneByName')
            ->with('Night Start')
            ->willReturn($this->createScheduleEntity(1, 'Night Start'));

        $service = new ScheduleService($entityManager, $scheduleRepository, $deviceRepository);

        $this->expectException(EntityAlreadyExists::class);
        $this->expectExceptionMessage("Schedule with name 'Night Start' already exists.");

        $service->createSchedule('Night Start', '0 2 * * *', ScheduleInterface::COMMAND_START, []);
    }

    public function testCreateScheduleThrowsWhenDeviceIdCannotBeResolved(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $scheduleRepository = $this->createMock(ScheduleRepository::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $device = $this->createDeviceEntity(1, 'node-1');

        $scheduleRepository->expects($this->once())->method('findOneByName')->with('Night Start')->willReturn(null);
        $deviceRepository->expects($this->once())->method('findByIds')->with([1, 2])->willReturn([$device]);

        $service = new ScheduleService($entityManager, $scheduleRepository, $deviceRepository);

        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessage("Device with id '2' not found.");

        $service->createSchedule('Night Start', '0 2 * * *', ScheduleInterface::COMMAND_START, [1, 2]);
    }

    public function testCreateSchedulePersistsAndLinksDevices(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $scheduleRepository = $this->createMock(ScheduleRepository::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $deviceA = $this->createDeviceEntity(1, 'node-1');
        $deviceB = $this->createDeviceEntity(2, 'node-2');

        $scheduleRepository->expects($this->once())->method('findOneByName')->with('Night Start')->willReturn(null);
        $deviceRepository->expects($this->once())->method('findByIds')->with([1, 2])->willReturn([$deviceA, $deviceB]);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = new ScheduleService($entityManager, $scheduleRepository, $deviceRepository);
        $schedule = $service->createSchedule(
            'Night Start',
            '0 2 * * *',
            ScheduleInterface::COMMAND_START,
            [1, 2],
            true
        );

        $this->assertSame('Night Start', $schedule->getName());
        $this->assertSame(ScheduleInterface::COMMAND_START, $schedule->getCommand());
        $this->assertTrue($schedule->isEnabled());
        $this->assertCount(2, $schedule->getDevices());
    }

    public function testUpdateScheduleThrowsWhenMissing(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $scheduleRepository = $this->createMock(ScheduleRepository::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);

        $scheduleRepository->expects($this->once())->method('findById')->with(77)->willReturn(null);

        $service = new ScheduleService($entityManager, $scheduleRepository, $deviceRepository);

        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessage("Schedule with id '77' not found.");

        $service->updateSchedule(77, 'Night Start', true, '0 2 * * *', ScheduleInterface::COMMAND_START, []);
    }

    public function testUpdateScheduleReplacesDevicesAndFlushes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $scheduleRepository = $this->createMock(ScheduleRepository::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $schedule = $this->createScheduleEntity(1, 'Night Start', '0 2 * * *', ScheduleInterface::COMMAND_START);
        $oldDevice = $this->createDeviceEntity(1, 'node-old');
        $newDevice = $this->createDeviceEntity(2, 'node-new');
        $schedule->addDevice($oldDevice);

        $scheduleRepository->expects($this->once())->method('findById')->with(1)->willReturn($schedule);
        $scheduleRepository->expects($this->once())
            ->method('findOneByName')
            ->with('Night Start')
            ->willReturn($schedule);
        $deviceRepository->expects($this->once())->method('findByIds')->with([2])->willReturn([$newDevice]);
        $entityManager->expects($this->once())->method('flush');

        $service = new ScheduleService($entityManager, $scheduleRepository, $deviceRepository);
        $updated = $service->updateSchedule(
            1,
            'Night Start',
            false,
            '30 3 * * *',
            ScheduleInterface::COMMAND_STOP,
            [2]
        );

        $this->assertFalse($updated->isEnabled());
        $this->assertSame('30 3 * * *', $updated->getCronExpression());
        $this->assertSame(ScheduleInterface::COMMAND_STOP, $updated->getCommand());
        $this->assertCount(1, $updated->getDevices());
        $this->assertSame('node-new', $updated->getDevices()->first()->getName());
    }
}
