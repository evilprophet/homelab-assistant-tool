<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Application;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Repository\ActionLogRepository;
use InvalidArgumentException;

class ActionLogService extends AbstractDatabaseService
{
    public const int DEFAULT_RETENTION_DAYS = 90;
    public const int DEFAULT_LIST_LIMIT = 25;

    protected const array ALLOWED_SOURCES = [
        ActionLog::SOURCE_CRON,
        ActionLog::SOURCE_CLI,
        ActionLog::SOURCE_WEB,
    ];
    protected const array ALLOWED_LEVELS = [
        ActionLog::LEVEL_INFO,
        ActionLog::LEVEL_WARNING,
        ActionLog::LEVEL_ERROR,
    ];

    public function __construct(
        EntityManagerInterface $entityManager,
        protected ActionLogRepository $actionLogRepository
    ) {
        parent::__construct($entityManager);
    }

    public function listActionLogs(
        ?string $source = null,
        ?string $level = null,
        ?string $action = null,
        ?int $limit = self::DEFAULT_LIST_LIMIT
    ): array {
        if ($source !== null) {
            $this->assertAllowedSource($source);
        }

        if ($level !== null) {
            $this->assertAllowedLevel($level);
        }

        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException(
                'List limit must be greater than or equal to 1.'
            );
        }

        return $this->actionLogRepository->findByFilters($source, $level, $action, $limit);
    }

    public static function getAllowedSources(): array
    {
        return self::ALLOWED_SOURCES;
    }

    public static function getAllowedLevels(): array
    {
        return self::ALLOWED_LEVELS;
    }

    public function createActionLog(
        string $source,
        string $action,
        string $level,
        string $message,
        ?DateTimeImmutable $createdAt = null
    ): ActionLog {
        $this->assertAllowedSource($source);
        $this->assertAllowedLevel($level);

        $actionLog = new ActionLog();
        $actionLog
            ->setSource($source)
            ->setAction($action)
            ->setLevel($level)
            ->setMessage($message)
            ->setCreatedAt($createdAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC')));

        $this->persist($actionLog);
        $this->flush();

        return $actionLog;
    }

    public function cleanupOlderThanDays(int $retentionDays, ?string $level = null): int
    {
        if ($retentionDays < 1) {
            throw new InvalidArgumentException(
                'Retention days must be greater than or equal to 1.'
            );
        }

        if ($level !== null) {
            $this->assertAllowedLevel($level);
        }

        $thresholdDateTime = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify(sprintf('-%d days', $retentionDays));

        return $this->actionLogRepository->deleteByFilters($thresholdDateTime, $level);
    }

    public function cleanupAll(?string $level = null): int
    {
        if ($level !== null) {
            $this->assertAllowedLevel($level);
            return $this->actionLogRepository->deleteByFilters(null, $level);
        }

        return $this->actionLogRepository->deleteAll();
    }

    public function listActionLogsPaginated(
        ?string $source,
        ?string $level,
        ?string $action,
        ?DateTimeImmutable $fromDateUtc,
        ?DateTimeImmutable $toDateUtc,
        ?string $entityText,
        int $page = 1,
        int $perPage = self::DEFAULT_LIST_LIMIT
    ): array {
        if ($source !== null) {
            $this->assertAllowedSource($source);
        }

        if ($level !== null) {
            $this->assertAllowedLevel($level);
        }

        if ($page < 1) {
            throw new InvalidArgumentException('Page must be greater than or equal to 1.');
        }

        if ($perPage < 1) {
            throw new InvalidArgumentException('Per-page limit must be greater than or equal to 1.');
        }

        return $this->actionLogRepository->findPaginatedByFilters(
            $source,
            $level,
            $action,
            $fromDateUtc,
            $toDateUtc,
            $entityText,
            $page,
            $perPage
        );
    }

    public function listDistinctActions(): array
    {
        return $this->actionLogRepository->findDistinctActions();
    }

    protected function assertAllowedSource(string $source): void
    {
        if (in_array($source, self::ALLOWED_SOURCES, true)) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                "Invalid action log source '%s'. Allowed values: %s.",
                $source,
                implode(', ', self::ALLOWED_SOURCES)
            )
        );
    }

    protected function assertAllowedLevel(string $level): void
    {
        if (in_array($level, self::ALLOWED_LEVELS, true)) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf(
                "Invalid action log level '%s'. Allowed values: %s.",
                $level,
                implode(', ', self::ALLOWED_LEVELS)
            )
        );
    }
}
