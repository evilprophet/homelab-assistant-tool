<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Runtime;

use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Contract\UpsInterface;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use EvilStudio\HAT\Service\Runtime\RuntimeStatusResolver;
use EvilStudio\HAT\Service\Runtime\UpsRuntimeService;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use PHPUnit\Framework\TestCase;

class RuntimeStatusResolverTest extends TestCase
{
    public function testResolveDeviceStatusByNamesNormalizesInputAndUsesCache(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $cache = new ArrayAdapter();

        $deviceOperations->expects($this->once())
            ->method('checkDeviceStatus')
            ->with('node-1')
            ->willReturn($runtimeDevice);
        $runtimeDevice->expects($this->once())->method('toArray')->willReturn(['status' => 'online']);

        $resolver = new RuntimeStatusResolver($deviceOperations, $upsRuntimeService, $cache);

        $first = $resolver->resolveDeviceStatusByNames([' node-1 ', '', 'node-1']);
        $second = $resolver->resolveDeviceStatusByNames(['node-1']);

        $this->assertSame(['node-1' => 'online'], $first);
        $this->assertSame(['node-1' => 'online'], $second);
    }

    public function testResolveDeviceStatusByNamesReturnsUnknownOnRuntimeFailure(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $cache = new ArrayAdapter();

        $deviceOperations->expects($this->once())
            ->method('checkDeviceStatus')
            ->with('node-1')
            ->willThrowException(new RuntimeException('Runtime unavailable.'));

        $resolver = new RuntimeStatusResolver($deviceOperations, $upsRuntimeService, $cache);
        $result = $resolver->resolveDeviceStatusByNames(['node-1']);

        $this->assertSame(['node-1' => 'unknown'], $result);
    }

    public function testResolveUpsStatusByIdentifiersReturnsStructuredStatus(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $runtimeUps = $this->createMock(UpsInterface::class);
        $cache = new ArrayAdapter();

        $upsRuntimeService->expects($this->once())
            ->method('getRuntimeUpsByIdentifier')
            ->with('ups-main')
            ->willReturn($runtimeUps);
        $runtimeUps->expects($this->once())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('toArray')->willReturn([
            'status' => 'On Battery',
            'battery' => "Current: 62%\nRuntime: 90 min\nLow Runtime Threshold: 10 min\nSafe Runtime Threshold: 30 min",
        ]);

        $resolver = new RuntimeStatusResolver($deviceOperations, $upsRuntimeService, $cache);
        $result = $resolver->resolveUpsStatusByIdentifiers(['ups-main']);

        $this->assertSame(
            [
                'ups-main' => [
                    'label' => 'On Battery',
                    'tone' => 'warning',
                    'battery_level' => 62,
                    'battery_runtime_minutes' => 90,
                ],
            ],
            $result
        );
    }

    public function testResolveUpsStatusByIdentifiersReturnsOnlineStatusWhenObFlagIsMissing(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $runtimeUps = $this->createMock(UpsInterface::class);
        $cache = new ArrayAdapter();

        $upsRuntimeService->expects($this->once())
            ->method('getRuntimeUpsByIdentifier')
            ->with('ups-main')
            ->willReturn($runtimeUps);
        $runtimeUps->expects($this->once())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('toArray')->willReturn([
            'status' => 'Online',
            'battery' => "Current: 99%\nRuntime: 153 min\nLow Runtime Threshold: 10 min\n"
                . 'Safe Runtime Threshold: N/A min',
        ]);

        $resolver = new RuntimeStatusResolver($deviceOperations, $upsRuntimeService, $cache);
        $result = $resolver->resolveUpsStatusByIdentifiers(['ups-main']);

        $this->assertSame(
            [
                'ups-main' => [
                    'label' => 'Online',
                    'tone' => 'success',
                    'battery_level' => 99,
                    'battery_runtime_minutes' => 153,
                ],
            ],
            $result
        );
    }

    public function testResolveUpsStatusByIdentifiersReturnsUnknownWhenStatusIsUnavailable(): void
    {
        $deviceOperations = $this->createMock(DeviceOperationsService::class);
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $runtimeUps = $this->createMock(UpsInterface::class);
        $cache = new ArrayAdapter();

        $upsRuntimeService->expects($this->once())
            ->method('getRuntimeUpsByIdentifier')
            ->with('ups-main')
            ->willReturn($runtimeUps);
        $runtimeUps->expects($this->once())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('toArray')->willReturn([
            'status' => 'Unknown',
            'battery' => '-',
        ]);

        $resolver = new RuntimeStatusResolver($deviceOperations, $upsRuntimeService, $cache);
        $result = $resolver->resolveUpsStatusByIdentifiers(['ups-main']);

        $this->assertSame(
            [
                'ups-main' => [
                    'label' => 'Unknown',
                    'tone' => 'neutral',
                    'battery_level' => null,
                    'battery_runtime_minutes' => null,
                ],
            ],
            $result
        );
    }
}
