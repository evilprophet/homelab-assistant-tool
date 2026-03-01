<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Controller;

use DateTimeImmutable;
use EvilStudio\HAT\Controller\ScheduleController;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Repository\ScheduleRepository;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use EvilStudio\HAT\Service\Runtime\ScheduleRuntimeService;
use RuntimeException;
use PHPUnit\Framework\TestCase;

class ScheduleControllerTest extends TestCase
{
    public function testBuildNextRunsReturnsDatesForValidCronExpression(): void
    {
        $controller = $this->createTestableController();

        $result = $controller->callBuildNextRuns('* * * * *', 2);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(DateTimeImmutable::class, $result);
    }

    public function testBuildNextRunsReturnsEmptyArrayForInvalidCronExpression(): void
    {
        $controller = $this->createTestableController();

        $result = $controller->callBuildNextRuns('invalid cron', 2);

        $this->assertSame([], $result);
    }

    public function testResolveStatusByDeviceNameReturnsUnknownMapFromRuntimeDevices(): void
    {
        $runtimeDevice = $this->createMock(DeviceInterface::class);
        $runtimeDevice->expects($this->once())->method('getName')->willReturn('node-1');
        $runtimeDevice->expects($this->once())->method('toArray')->willReturn(['status' => 'online']);

        $controller = $this->createTestableController([$runtimeDevice]);
        $result = $controller->callResolveStatusByDeviceName();

        $this->assertSame(['node-1' => 'online'], $result);
    }

    public function testResolveStatusByDeviceNameReturnsEmptyArrayOnRuntimeFailure(): void
    {
        $deviceOperationsService = $this->createMock(DeviceOperationsService::class);
        $deviceOperationsService->expects($this->once())
            ->method('listDevices')
            ->with(true)
            ->willThrowException(new RuntimeException('Runtime failed.'));

        $controller = $this->createTestableController([], $deviceOperationsService);
        $result = $controller->callResolveStatusByDeviceName();

        $this->assertSame([], $result);
    }

    protected function createTestableController(
        array $runtimeDevices = [],
        ?DeviceOperationsService $deviceOperationsService = null
    ): object {
        $scheduleService = $this->createMock(ScheduleService::class);
        $scheduleRuntimeService = $this->createMock(ScheduleRuntimeService::class);
        $deviceService = $this->createMock(DeviceService::class);
        $scheduleRepository = $this->createMock(ScheduleRepository::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $configuration = $this->createMock(Configuration::class);
        $configuration->method('getTimezone')->willReturn('UTC');

        if ($deviceOperationsService === null) {
            $deviceOperationsService = $this->createMock(DeviceOperationsService::class);
            $deviceOperationsService->method('listDevices')->with(true)->willReturn($runtimeDevices);
        }

        return new class (
            $scheduleService,
            $scheduleRuntimeService,
            $deviceService,
            $scheduleRepository,
            $actionLogService,
            $deviceOperationsService,
            $configuration
        ) extends ScheduleController {
            public function callBuildNextRuns(string $cronExpression, int $count = 3): array
            {
                return $this->buildNextRuns($cronExpression, $count);
            }

            public function callResolveStatusByDeviceName(): array
            {
                return $this->resolveStatusByDeviceName();
            }
        };
    }
}
