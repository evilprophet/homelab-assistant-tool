<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Controller;

use DateTimeImmutable;
use DateTimeZone;
use EvilStudio\HAT\Controller\LogsController;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Application\ActionLogService;
use PHPUnit\Framework\TestCase;

class LogsControllerTest extends TestCase
{
    public function testResolveSourceFilterAcceptsAllowedValue(): void
    {
        $controller = $this->createTestableController();
        $errors = [];

        $resolved = $controller->callResolveSourceFilter('CLI', $errors);

        $this->assertSame('CLI', $resolved);
        $this->assertSame([], $errors);
    }

    public function testResolveLevelFilterRejectsUnknownValue(): void
    {
        $controller = $this->createTestableController();
        $errors = [];

        $resolved = $controller->callResolveLevelFilter('critical', $errors);

        $this->assertNull($resolved);
        $this->assertSame(['Unknown level filter: critical.'], $errors);
    }

    public function testResolveDateFilterParsesLocalDateToUtc(): void
    {
        $controller = $this->createTestableController();
        $errors = [];
        $timezone = new DateTimeZone('Europe/Warsaw');

        $resolved = $controller->callResolveDateFilter('2026-02-28', false, $timezone, 'from', $errors);

        $this->assertInstanceOf(DateTimeImmutable::class, $resolved);
        $this->assertSame('UTC', $resolved?->getTimezone()->getName());
        $this->assertSame([], $errors);
    }

    protected function createTestableController(): object
    {
        $actionLogService = $this->createMock(ActionLogService::class);
        $configuration = $this->createMock(Configuration::class);

        return new class ($actionLogService, $configuration) extends LogsController {
            public function callResolveSourceFilter(string $value, array &$errors): ?string
            {
                return $this->resolveSourceFilter($value, $errors);
            }

            public function callResolveLevelFilter(string $value, array &$errors): ?string
            {
                return $this->resolveLevelFilter($value, $errors);
            }

            public function callResolveDateFilter(
                string $date,
                bool $isEndOfDay,
                DateTimeZone $timezone,
                string $fieldName,
                array &$errors
            ): ?DateTimeImmutable {
                return $this->resolveDateFilter($date, $isEndOfDay, $timezone, $fieldName, $errors);
            }
        };
    }
}
