<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Runtime;

use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Exception\UnsupportedDeviceAction;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use EvilStudio\HAT\Service\Runtime\DeviceRuntimeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class DeviceOperationsServiceTest extends TestCase
{
    public function testListDevicesWithStatusChecksEachDeviceStatus(): void
    {
        $runtimeService = $this->createMock(DeviceRuntimeService::class);
        $deviceA = $this->createMock(DeviceInterface::class);
        $deviceB = $this->createMock(DeviceInterface::class);

        $runtimeService->expects($this->once())
            ->method('listRuntimeDevices')
            ->willReturn(['node-1' => $deviceA, 'node-2' => $deviceB]);
        $deviceA->expects($this->once())->method('checkStatus');
        $deviceB->expects($this->once())->method('checkStatus');

        $service = new DeviceOperationsService($runtimeService);
        $result = $service->listDevices(true);

        $this->assertCount(2, $result);
    }

    public function testCheckStartStopAndSshDelegateToRuntimeDevice(): void
    {
        $runtimeService = $this->createMock(DeviceRuntimeService::class);
        $device = $this->createMock(DeviceInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $runtimeService->expects($this->exactly(4))
            ->method('getRuntimeDeviceByName')
            ->with('node-1')
            ->willReturn($device);
        $device->expects($this->exactly(3))->method('getPlatform')->willReturn('linux');
        $device->expects($this->once())->method('checkStatus');
        $device->expects($this->once())->method('start')->willReturn(true);
        $device->expects($this->once())->method('stop')->willReturn(false);
        $device->expects($this->once())->method('ssh')->with($output);

        $service = new DeviceOperationsService($runtimeService);

        $this->assertSame($device, $service->checkDeviceStatus('node-1'));
        $this->assertTrue($service->startDevice('node-1'));
        $this->assertFalse($service->stopDevice('node-1'));
        $service->sshIntoDevice('node-1', $output);
    }

    public function testStopThrowsUnsupportedDeviceActionWhenPlatformDoesNotSupportStop(): void
    {
        $runtimeService = $this->createMock(DeviceRuntimeService::class);
        $device = $this->createMock(DeviceInterface::class);

        $runtimeService->expects($this->once())
            ->method('getRuntimeDeviceByName')
            ->with('node-1')
            ->willReturn($device);
        $device->expects($this->once())->method('getPlatform')->willReturn('synology_dsm');
        $device->expects($this->never())->method('stop');

        $service = new DeviceOperationsService($runtimeService);

        $this->expectException(UnsupportedDeviceAction::class);
        $this->expectExceptionMessage("Stop action is not supported on 'synology_dsm' device.");
        $service->stopDevice('node-1');
    }
}
