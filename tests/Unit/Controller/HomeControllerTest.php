<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Controller;

use DateTimeImmutable;
use EvilStudio\HAT\Controller\HomeController;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use EvilStudio\HAT\Service\Runtime\ScheduleRuntimeService;
use EvilStudio\HAT\Service\Runtime\UpsRuntimeService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;

class HomeControllerTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testResolveNextRunByScheduleIdReturnsNextRunForValidCron(): void
    {
        $schedule = ['id' => 1, 'name' => 'Night Start', 'cron_expression' => '*/5 * * * *'];
        $controller = $this->createTestableController('UTC');

        $result = $controller->callResolveNextRunByScheduleId([$schedule]);

        $this->assertArrayHasKey(1, $result);
        $this->assertInstanceOf(DateTimeImmutable::class, $result[1]);
    }

    public function testResolveNextRunByScheduleIdSkipsInvalidCronExpression(): void
    {
        $schedule = ['id' => 2, 'name' => 'Broken', 'cron_expression' => 'invalid cron'];
        $controller = $this->createTestableController('UTC');

        $result = $controller->callResolveNextRunByScheduleId([$schedule]);

        $this->assertSame([], $result);
    }

    public function testResolveNextRunByScheduleIdFallsBackToUtcWhenTimezoneInvalid(): void
    {
        $schedule = ['id' => 3, 'name' => 'Night Stop', 'cron_expression' => '0 * * * *'];
        $controller = $this->createTestableController('Invalid/Timezone');

        $result = $controller->callResolveNextRunByScheduleId([$schedule]);

        $this->assertArrayHasKey(3, $result);
        $this->assertSame('UTC', $result[3]->getTimezone()->getName());
    }

    protected function createTestableController(string $timezone): object
    {
        $configuration = new Configuration([
            'cron' => true,
            'ups_mode' => true,
            'ssh_key_path' => '/tmp/test-key',
            'default_ssh_username' => 'root',
            'timezone' => $timezone,
        ]);

        return new class (
            $this->createMock(DeviceOperationsService::class),
            $this->createMock(UpsRuntimeService::class),
            $this->createMock(ScheduleRuntimeService::class),
            $this->createMock(ActionLogService::class),
            $configuration
        ) extends HomeController {
            public function callResolveNextRunByScheduleId(array $schedules): array
            {
                return $this->resolveNextRunByScheduleId($schedules);
            }
        };
    }
}
