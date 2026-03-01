<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Application;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Repository\ActionLogRepository;
use EvilStudio\HAT\Service\Application\ActionLogService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ActionLogServiceTest extends TestCase
{
    public function testCreateActionLogPersistsEntity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(ActionLogRepository::class);
        $createdAt = new DateTimeImmutable('2026-01-01 10:00:00');

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(ActionLog::class));
        $entityManager->expects($this->once())->method('flush');

        $service = new ActionLogService($entityManager, $repository);
        $actionLog = $service->createActionLog(
            ActionLog::SOURCE_CLI,
            'device.start',
            ActionLog::LEVEL_INFO,
            'Started device',
            $createdAt
        );

        $this->assertSame(ActionLog::SOURCE_CLI, $actionLog->getSource());
        $this->assertSame('device.start', $actionLog->getAction());
        $this->assertSame(ActionLog::LEVEL_INFO, $actionLog->getLevel());
        $this->assertSame('Started device', $actionLog->getMessage());
        $this->assertSame($createdAt, $actionLog->getCreatedAt());
    }

    public function testCreateActionLogRejectsInvalidSource(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(ActionLogRepository::class);

        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $service = new ActionLogService($entityManager, $repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid action log source 'API'");

        $service->createActionLog('API', 'device.start', ActionLog::LEVEL_INFO, 'Message');
    }

    public function testListActionLogsRejectsInvalidLimit(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(ActionLogRepository::class);
        $repository->expects($this->never())->method('findByFilters');

        $service = new ActionLogService($entityManager, $repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('List limit must be greater than or equal to 1.');

        $service->listActionLogs(limit: 0);
    }

    public function testCleanupOlderThanDaysDelegatesToRepository(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(ActionLogRepository::class);

        $repository->expects($this->once())
            ->method('deleteByFilters')
            ->with(
                $this->callback(static fn ($value): bool => $value instanceof DateTimeImmutable),
                ActionLog::LEVEL_WARNING
            )
            ->willReturn(3);

        $service = new ActionLogService($entityManager, $repository);

        $this->assertSame(3, $service->cleanupOlderThanDays(30, ActionLog::LEVEL_WARNING));
    }
}
