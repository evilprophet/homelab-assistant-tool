<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Service\Application;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\DeviceRepository;
use EvilStudio\HAT\Repository\ScheduleRepository;
use EvilStudio\HAT\Repository\UpsRepository;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Integration\Support\DatabaseIntegrationTestCase;

class DeviceServiceDatabaseIntegrationTest extends DatabaseIntegrationTestCase
{
    protected DeviceService $deviceService;
    protected UpsService $upsService;
    protected ScheduleService $scheduleService;

    protected function setUp(): void
    {
        parent::setUp();

        $deviceRepository = new DeviceRepository($this->entityManager);
        $upsRepository = new UpsRepository($this->entityManager);
        $scheduleRepository = new ScheduleRepository($this->entityManager);

        $this->deviceService = new DeviceService($this->entityManager, $deviceRepository, $upsRepository);
        $this->upsService = new UpsService($this->entityManager, $upsRepository);
        $this->scheduleService = new ScheduleService($this->entityManager, $scheduleRepository, $deviceRepository);
    }

    public function testCreateUpdateAndRemoveDeviceWithScheduleLinks(): void
    {
        $ups = $this->upsService->createUps('Main UPS', 'ups-main', 'ups.local', 600);
        $device = $this->deviceService->createDevice(
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::LINUX->value,
            'root',
            300,
            $ups->getId()
        );

        $this->assertSame('node-1', $device->getName());
        $this->assertSame($ups->getId(), $device->getUps()?->getId());

        $updated = $this->deviceService->updateDevice(
            (int)$device->getId(),
            'node-main',
            '10.0.0.11',
            '00:11:22:33:44:66',
            DevicePlatform::UBUNTU->value,
            'admin',
            120,
            $ups->getId()
        );

        $this->assertSame('node-main', $updated->getName());
        $this->assertSame(DevicePlatform::UBUNTU->value, $updated->getPlatform());

        $schedule = $this->scheduleService->createSchedule(
            'Night Start',
            '0 2 * * *',
            ScheduleInterface::COMMAND_START,
            [(int)$updated->getId()],
            true
        );
        $this->assertCount(1, $schedule->getDevices());

        $this->deviceService->removeDevice((int)$updated->getId());

        $scheduleAfterDeviceRemoval = $this->scheduleService->getScheduleById((int)$schedule->getId());
        $this->assertCount(0, $scheduleAfterDeviceRemoval->getDevices());

        $this->expectException(EntityNotFound::class);
        $this->deviceService->getDeviceByName('node-main');
    }

    public function testConcurrentInsertSurfacesAsEntityAlreadyExistsInsteadOfRawSqlError(): void
    {
        // Writes the conflicting row straight to the DB between the uniqueness
        // SELECT and the flush - exactly the window a second process races through.
        $service = new class (
            $this->entityManager,
            new DeviceRepository($this->entityManager),
            new UpsRepository($this->entityManager)
        ) extends DeviceService {
            protected function ensureNameIsUnique(string $name, ?int $excludeDeviceId = null): void
            {
                parent::ensureNameIsUnique($name, $excludeDeviceId);

                $this->entityManager->getConnection()->insert('devices', [
                    'name' => $name,
                    'ip' => '10.0.0.99',
                    'mac' => '00:11:22:33:44:99',
                    'platform' => 'generic',
                    'auto_stop_allowed' => 1,
                ]);
            }
        };

        $this->expectException(EntityAlreadyExists::class);
        $this->expectExceptionMessage("Device with name 'node-raced' already exists.");

        $service->createDevice('node-raced', '10.0.0.10', '00:11:22:33:44:55', DevicePlatform::LINUX->value);
    }
}
