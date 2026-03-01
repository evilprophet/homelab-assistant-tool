<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Repository;

use DateTimeImmutable;
use DateTimeZone;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Repository\ActionLogRepository;
use EvilStudio\HAT\Tests\Integration\Support\DatabaseIntegrationTestCase;

class ActionLogRepositoryTest extends DatabaseIntegrationTestCase
{
    protected ActionLogRepository $actionLogRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actionLogRepository = new ActionLogRepository($this->entityManager);
    }

    public function testFindByFiltersAppliesSourceLevelActionAndLimit(): void
    {
        $this->seedLogs();

        $logs = $this->actionLogRepository->findByFilters(
            ActionLog::SOURCE_WEB,
            ActionLog::LEVEL_INFO,
            'ups.update',
            1
        );

        $this->assertCount(1, $logs);
        $this->assertSame(ActionLog::SOURCE_WEB, $logs[0]->getSource());
        $this->assertSame(ActionLog::LEVEL_INFO, $logs[0]->getLevel());
        $this->assertSame('ups.update', $logs[0]->getAction());
    }

    public function testFindPaginatedByFiltersAppliesDateEntityAndOrdering(): void
    {
        $this->seedLogs();

        $result = $this->actionLogRepository->findPaginatedByFilters(
            ActionLog::SOURCE_WEB,
            null,
            null,
            $this->utc('2026-02-28 10:30:00'),
            $this->utc('2026-02-28 13:00:00'),
            'node-1',
            1,
            2
        );

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['items']);
        $this->assertSame('ups.update', $result['items'][0]->getAction());
        $this->assertSame('device.stop', $result['items'][1]->getAction());
    }

    public function testFindDistinctActionsReturnsUniqueSortedValues(): void
    {
        $this->seedLogs();

        $this->assertSame(
            ['device.start', 'device.stop', 'ups.update'],
            $this->actionLogRepository->findDistinctActions()
        );
    }

    public function testDeleteByFiltersRemovesOnlyMatchingRows(): void
    {
        $this->seedLogs();

        $removed = $this->actionLogRepository->deleteByFilters(
            $this->utc('2026-02-28 12:30:00'),
            ActionLog::LEVEL_INFO
        );

        $this->assertSame(2, $removed);
        $this->assertCount(2, $this->actionLogRepository->findAll());
    }

    protected function seedLogs(): void
    {
        $this->persistActionLog(
            ActionLog::SOURCE_WEB,
            ActionLog::LEVEL_INFO,
            'device.start',
            'node-1 started',
            $this->utc('2026-02-28 10:00:00')
        );
        $this->persistActionLog(
            ActionLog::SOURCE_WEB,
            ActionLog::LEVEL_WARNING,
            'device.stop',
            'node-1 stopped due low battery',
            $this->utc('2026-02-28 11:00:00')
        );
        $this->persistActionLog(
            ActionLog::SOURCE_CLI,
            ActionLog::LEVEL_INFO,
            'device.start',
            'node-2 started from CLI',
            $this->utc('2026-02-28 12:00:00')
        );
        $this->persistActionLog(
            ActionLog::SOURCE_WEB,
            ActionLog::LEVEL_INFO,
            'ups.update',
            'node-1 runtime refreshed',
            $this->utc('2026-02-28 13:00:00')
        );

        $this->entityManager->flush();
    }

    protected function persistActionLog(
        string $source,
        string $level,
        string $action,
        string $message,
        DateTimeImmutable $createdAt
    ): void {
        $this->entityManager->persist(
            (new ActionLog())
                ->setSource($source)
                ->setLevel($level)
                ->setAction($action)
                ->setMessage($message)
                ->setCreatedAt($createdAt)
        );
    }

    protected function utc(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
