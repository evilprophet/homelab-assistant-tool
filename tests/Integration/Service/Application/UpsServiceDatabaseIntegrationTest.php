<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Service\Application;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\DeviceRepository;
use EvilStudio\HAT\Repository\UpsRepository;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Integration\Support\DatabaseIntegrationTestCase;

class UpsServiceDatabaseIntegrationTest extends DatabaseIntegrationTestCase
{
    protected UpsService $upsService;
    protected DeviceService $deviceService;

    protected function setUp(): void
    {
        parent::setUp();

        $deviceRepository = new DeviceRepository($this->entityManager);
        $upsRepository = new UpsRepository($this->entityManager);

        $this->upsService = new UpsService($this->entityManager, $upsRepository);
        $this->deviceService = new DeviceService($this->entityManager, $deviceRepository, $upsRepository);
    }

    public function testRemoveUpsDetachesLinkedDevices(): void
    {
        $ups = $this->upsService->createUps('Main UPS', 'ups-main', 'ups.local', 600);
        $device = $this->deviceService->createDevice(
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::GENERIC->value,
            null,
            null,
            (int)$ups->getId()
        );

        $this->assertSame($ups->getId(), $device->getUps()?->getId());

        $this->upsService->removeUps((int)$ups->getId());
        $this->entityManager->clear();

        $detachedDevice = $this->deviceService->getDeviceByName('node-1');
        $this->assertNull($detachedDevice->getUps());

        $this->expectException(EntityNotFound::class);
        $this->upsService->getUpsByIdentifier('ups-main');
    }
}
