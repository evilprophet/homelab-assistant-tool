<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Service\Application;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Repository\DeviceRepository;
use EvilStudio\HAT\Repository\ScheduleRepository;
use EvilStudio\HAT\Repository\UpsRepository;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Tests\Integration\Support\DatabaseIntegrationTestCase;

class ScheduleServiceDatabaseIntegrationTest extends DatabaseIntegrationTestCase
{
    protected ScheduleService $scheduleService;
    protected DeviceService $deviceService;

    protected function setUp(): void
    {
        parent::setUp();

        $deviceRepository = new DeviceRepository($this->entityManager);
        $upsRepository = new UpsRepository($this->entityManager);
        $scheduleRepository = new ScheduleRepository($this->entityManager);

        $this->scheduleService = new ScheduleService($this->entityManager, $scheduleRepository, $deviceRepository);
        $this->deviceService = new DeviceService($this->entityManager, $deviceRepository, $upsRepository);
    }

    public function testCreateAndUpdateScheduleWithDeviceRelations(): void
    {
        $deviceA = $this->deviceService->createDevice(
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::GENERIC->value
        );
        $deviceB = $this->deviceService->createDevice(
            'node-2',
            '10.0.0.11',
            '00:11:22:33:44:66',
            DevicePlatform::GENERIC->value
        );

        $schedule = $this->scheduleService->createSchedule(
            'Night Start',
            '0 2 * * *',
            ScheduleInterface::COMMAND_START,
            [(int)$deviceA->getId(), (int)$deviceA->getId(), (int)$deviceB->getId()],
            true
        );

        $this->assertSame('Night Start', $schedule->getName());
        $this->assertCount(2, $schedule->getDevices());

        $updated = $this->scheduleService->updateSchedule(
            (int)$schedule->getId(),
            'Night Stop',
            false,
            '30 4 * * *',
            ScheduleInterface::COMMAND_STOP,
            [(int)$deviceB->getId()]
        );

        $this->assertSame('Night Stop', $updated->getName());
        $this->assertFalse($updated->isEnabled());
        $this->assertSame(ScheduleInterface::COMMAND_STOP, $updated->getCommand());
        $this->assertCount(1, $updated->getDevices());
        $this->assertSame('node-2', $updated->getDevices()->first()?->getName());
    }
}
