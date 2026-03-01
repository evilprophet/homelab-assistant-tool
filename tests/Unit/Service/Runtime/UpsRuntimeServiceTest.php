<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Runtime;

use EvilStudio\HAT\Contract\UpsInterface;
use EvilStudio\HAT\Factory\RuntimeUpsFactory;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Service\Runtime\UpsRuntimeService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;

class UpsRuntimeServiceTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testListRuntimeUpsBuildsMapByIdentifier(): void
    {
        $upsService = $this->createMock(UpsService::class);
        $runtimeUpsFactory = $this->createMock(RuntimeUpsFactory::class);
        $upsA = $this->createUpsEntity(1, 'Main UPS', 'ups-main', 'ups-main.local');
        $upsB = $this->createUpsEntity(2, 'Backup UPS', 'ups-backup', 'ups-backup.local');
        $runtimeA = $this->createMock(UpsInterface::class);
        $runtimeB = $this->createMock(UpsInterface::class);

        $upsService->expects($this->once())->method('listUps')->willReturn([$upsA, $upsB]);
        $runtimeUpsFactory->expects($this->exactly(2))
            ->method('createFromEntity')
            ->with($this->logicalOr($upsA, $upsB))
            ->willReturnOnConsecutiveCalls($runtimeA, $runtimeB);
        $runtimeA->expects($this->once())->method('getIdentifier')->willReturn('ups-main');
        $runtimeB->expects($this->once())->method('getIdentifier')->willReturn('ups-backup');

        $service = new UpsRuntimeService($upsService, $runtimeUpsFactory);
        $result = $service->listRuntimeUps();

        $this->assertSame(['ups-main', 'ups-backup'], array_keys($result));
        $this->assertSame($runtimeA, $result['ups-main']);
        $this->assertSame($runtimeB, $result['ups-backup']);
    }

    public function testIsAnyUpsOnBatteryReturnsTrueWhenAnyRuntimeUpsIsOnBattery(): void
    {
        $upsService = $this->createMock(UpsService::class);
        $runtimeUpsFactory = $this->createMock(RuntimeUpsFactory::class);
        $upsA = $this->createUpsEntity(1, 'Main UPS', 'ups-main', 'ups-main.local');
        $upsB = $this->createUpsEntity(2, 'Backup UPS', 'ups-backup', 'ups-backup.local');
        $runtimeA = $this->createMock(UpsInterface::class);
        $runtimeB = $this->createMock(UpsInterface::class);

        $upsService->expects($this->once())->method('listUps')->willReturn([$upsA, $upsB]);
        $runtimeUpsFactory->expects($this->exactly(2))
            ->method('createFromEntity')
            ->with($this->logicalOr($upsA, $upsB))
            ->willReturnOnConsecutiveCalls($runtimeA, $runtimeB);
        $runtimeA->method('getIdentifier')->willReturn('ups-main');
        $runtimeB->method('getIdentifier')->willReturn('ups-backup');
        $runtimeA->expects($this->once())->method('isOnBattery')->willReturn(false);
        $runtimeB->expects($this->once())->method('isOnBattery')->willReturn(true);

        $service = new UpsRuntimeService($upsService, $runtimeUpsFactory);

        $this->assertTrue($service->isAnyUpsOnBattery());
    }
}
