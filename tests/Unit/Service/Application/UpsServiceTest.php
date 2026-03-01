<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Application;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\UpsRepository;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UpsServiceTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testCreateUpsThrowsWhenIdentifierAlreadyExists(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $upsRepository->expects($this->once())
            ->method('findOneByIdentifier')
            ->with('ups-main')
            ->willReturn($this->createUpsEntity(1, 'Main UPS', 'ups-main', 'ups.local'));

        $service = new UpsService($entityManager, $upsRepository);

        $this->expectException(EntityAlreadyExists::class);
        $this->expectExceptionMessage("UPS with identifier 'ups-main' already exists.");

        $service->createUps('Main UPS', 'ups-main', 'ups.local');
    }

    public function testCreateUpsPersistsEntity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $upsRepository->expects($this->once())->method('findOneByIdentifier')->with('ups-main')->willReturn(null);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = new UpsService($entityManager, $upsRepository);
        $ups = $service->createUps('Main UPS', 'ups-main', 'ups.local', 900);

        $this->assertSame('Main UPS', $ups->getName());
        $this->assertSame('ups-main', $ups->getIdentifier());
        $this->assertSame('ups.local', $ups->getHost());
        $this->assertSame(900, $ups->getSafeBatteryRuntimeThreshold());
    }

    public function testCreateUpsNormalizesIdentifierAndHostBeforePersist(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $upsRepository->expects($this->once())->method('findOneByIdentifier')->with('ups-main')->willReturn(null);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = new UpsService($entityManager, $upsRepository);
        $ups = $service->createUps('Main UPS', '  ups-main  ', '  ups.local:3493  ', 900);

        $this->assertSame('ups-main', $ups->getIdentifier());
        $this->assertSame('ups.local:3493', $ups->getHost());
    }

    public function testCreateUpsThrowsWhenIdentifierContainsUnsafeCharacters(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $upsRepository = $this->createMock(UpsRepository::class);
        $upsRepository->expects($this->never())->method('findOneByIdentifier');
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new UpsService($entityManager, $upsRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UPS identifier can contain only letters, digits, dot, underscore, and dash.');

        $service->createUps('Main UPS', 'ups-main; rm -rf /', 'ups.local');
    }

    public function testCreateUpsThrowsWhenHostContainsUnsafeCharacters(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $upsRepository = $this->createMock(UpsRepository::class);
        $upsRepository->expects($this->never())->method('findOneByIdentifier');
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new UpsService($entityManager, $upsRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UPS host must be a valid hostname or IP with optional :port');

        $service->createUps('Main UPS', 'ups-main', 'ups.local && whoami');
    }

    public function testCreateUpsThrowsWhenHostPortIsOutOfRange(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $upsRepository = $this->createMock(UpsRepository::class);
        $upsRepository->expects($this->never())->method('findOneByIdentifier');
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new UpsService($entityManager, $upsRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UPS host port must be between 1 and 65535.');

        $service->createUps('Main UPS', 'ups-main', 'ups.local:70000');
    }

    public function testUpdateUpsThrowsWhenIdIsMissing(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $upsRepository->expects($this->once())->method('findById')->with(77)->willReturn(null);

        $service = new UpsService($entityManager, $upsRepository);

        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessage("UPS with id '77' not found.");

        $service->updateUps(77, 'Name', 'identifier', 'host');
    }

    public function testUpdateUpsThrowsWhenIdentifierBelongsToAnotherUps(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $upsRepository = $this->createMock(UpsRepository::class);
        $currentUps = $this->createUpsEntity(1, 'Main UPS', 'ups-main', 'ups.local');
        $otherUps = $this->createUpsEntity(2, 'Backup UPS', 'ups-backup', 'ups2.local');

        $upsRepository->expects($this->once())->method('findById')->with(1)->willReturn($currentUps);
        $upsRepository->expects($this->once())
            ->method('findOneByIdentifier')
            ->with('ups-backup')
            ->willReturn($otherUps);

        $service = new UpsService($entityManager, $upsRepository);

        $this->expectException(EntityAlreadyExists::class);
        $this->expectExceptionMessage("UPS with identifier 'ups-backup' already exists.");

        $service->updateUps(1, 'Main UPS', 'ups-backup', 'ups.local');
    }

    public function testRemoveUpsDetachesLinkedDevicesAndRemovesEntity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $upsRepository = $this->createMock(UpsRepository::class);
        $connection = $this->createMock(Connection::class);
        $ups = $this->createUpsEntity(1, 'Main UPS', 'ups-main', 'ups.local');
        $device = $this->createDeviceEntity(10, 'node-1');
        $ups->addDevice($device);

        $upsRepository->expects($this->once())->method('findById')->with(1)->willReturn($ups);
        $entityManager->expects($this->once())->method('getConnection')->willReturn($connection);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('commit');
        $entityManager->expects($this->once())->method('remove')->with($ups);
        $entityManager->expects($this->once())->method('flush');

        $service = new UpsService($entityManager, $upsRepository);
        $service->removeUps(1);

        $this->assertNull($device->getUps());
    }
}
