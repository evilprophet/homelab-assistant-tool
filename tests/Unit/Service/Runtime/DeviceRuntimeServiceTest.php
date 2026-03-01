<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Runtime;

use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Factory\RuntimeDeviceFactory;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Runtime\DeviceRuntimeService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;

class DeviceRuntimeServiceTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testListRuntimeDevicesBuildsMapByDeviceName(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $runtimeDeviceFactory = $this->createMock(RuntimeDeviceFactory::class);
        $deviceA = $this->createDeviceEntity(1, 'node-1');
        $deviceB = $this->createDeviceEntity(2, 'node-2');
        $runtimeA = $this->createMock(DeviceInterface::class);
        $runtimeB = $this->createMock(DeviceInterface::class);

        $deviceService->expects($this->once())->method('listDevices')->willReturn([$deviceA, $deviceB]);
        $runtimeDeviceFactory->expects($this->exactly(2))
            ->method('createFromEntity')
            ->with($this->logicalOr($deviceA, $deviceB))
            ->willReturnOnConsecutiveCalls($runtimeA, $runtimeB);
        $runtimeA->expects($this->once())->method('getName')->willReturn('node-1');
        $runtimeB->expects($this->once())->method('getName')->willReturn('node-2');

        $service = new DeviceRuntimeService($deviceService, $runtimeDeviceFactory);
        $result = $service->listRuntimeDevices();

        $this->assertSame(['node-1', 'node-2'], array_keys($result));
        $this->assertSame($runtimeA, $result['node-1']);
        $this->assertSame($runtimeB, $result['node-2']);
    }

    public function testGetRuntimeDeviceByNameDelegatesToServiceAndFactory(): void
    {
        $deviceService = $this->createMock(DeviceService::class);
        $runtimeDeviceFactory = $this->createMock(RuntimeDeviceFactory::class);
        $device = $this->createDeviceEntity(1, 'node-1');
        $runtimeDevice = $this->createMock(DeviceInterface::class);

        $deviceService->expects($this->once())
            ->method('getDeviceByName')
            ->with('node-1')
            ->willReturn($device);
        $runtimeDeviceFactory->expects($this->once())
            ->method('createFromEntity')
            ->with($device)
            ->willReturn($runtimeDevice);

        $service = new DeviceRuntimeService($deviceService, $runtimeDeviceFactory);

        $this->assertSame($runtimeDevice, $service->getRuntimeDeviceByName('node-1'));
    }
}
