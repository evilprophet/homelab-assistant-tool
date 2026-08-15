<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\ActionLog;

class ActionLogRepository
{
    protected const string LIKE_ESCAPE_CHARACTER = '!';

    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
    }

    public function findByFilters(
        ?string $source,
        ?string $level,
        ?string $action,
        int $limit
    ): array {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('actionLog')
            ->from(ActionLog::class, 'actionLog')
            ->orderBy('actionLog.id', 'DESC');

        if ($source !== null) {
            $queryBuilder
                ->andWhere('actionLog.source = :source')
                ->setParameter('source', $source);
        }

        if ($level !== null) {
            $queryBuilder
                ->andWhere('actionLog.level = :level')
                ->setParameter('level', $level);
        }

        if ($action !== null) {
            $queryBuilder
                ->andWhere('actionLog.action = :action')
                ->setParameter('action', $action);
        }

        $queryBuilder->setMaxResults($limit);

        $actionLogs = $queryBuilder->getQuery()->getResult();

        return array_values(array_filter($actionLogs, static fn ($actionLog) => $actionLog instanceof ActionLog));
    }

    public function findPaginatedByFilters(
        ?string $source,
        ?string $level,
        ?string $action,
        ?DateTimeImmutable $fromDateUtc,
        ?DateTimeImmutable $toDateUtc,
        ?string $entityText,
        int $page,
        int $perPage
    ): array {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('actionLog')
            ->from(ActionLog::class, 'actionLog')
            ->orderBy('actionLog.createdAt', 'DESC')
            ->addOrderBy('actionLog.id', 'DESC');

        if ($source !== null) {
            $queryBuilder
                ->andWhere('actionLog.source = :source')
                ->setParameter('source', $source);
        }

        if ($level !== null) {
            $queryBuilder
                ->andWhere('actionLog.level = :level')
                ->setParameter('level', $level);
        }

        if ($action !== null) {
            $queryBuilder
                ->andWhere('actionLog.action = :action')
                ->setParameter('action', $action);
        }

        if ($fromDateUtc !== null) {
            $queryBuilder
                ->andWhere('actionLog.createdAt >= :fromDateUtc')
                ->setParameter('fromDateUtc', $fromDateUtc);
        }

        if ($toDateUtc !== null) {
            $queryBuilder
                ->andWhere('actionLog.createdAt <= :toDateUtc')
                ->setParameter('toDateUtc', $toDateUtc);
        }

        if ($entityText !== null && $entityText !== '') {
            $queryBuilder
                ->andWhere(
                    sprintf(
                        "LOWER(actionLog.message) LIKE :entityText ESCAPE '%s'"
                        . " OR LOWER(actionLog.action) LIKE :entityText ESCAPE '%s'",
                        self::LIKE_ESCAPE_CHARACTER,
                        self::LIKE_ESCAPE_CHARACTER
                    )
                )
                ->setParameter('entityText', sprintf('%%%s%%', $this->escapeLikeTerm(mb_strtolower($entityText))));
        }

        $countQueryBuilder = clone $queryBuilder;
        $total = (int)$countQueryBuilder
            ->select('COUNT(actionLog.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $offset = ($page - 1) * $perPage;
        $actionLogs = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $items = array_values(array_filter($actionLogs, static fn ($actionLog) => $actionLog instanceof ActionLog));

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    protected function escapeLikeTerm(string $term): string
    {
        $escapeCharacter = self::LIKE_ESCAPE_CHARACTER;

        return str_replace(
            [$escapeCharacter, '%', '_'],
            [$escapeCharacter . $escapeCharacter, $escapeCharacter . '%', $escapeCharacter . '_'],
            $term
        );
    }

    public function findDistinctActions(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT actionLog.action as action')
            ->from(ActionLog::class, 'actionLog')
            ->orderBy('actionLog.action', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $actions = [];
        foreach ($rows as $row) {
            $action = $row['action'] ?? null;
            if (!is_string($action) || $action === '') {
                continue;
            }

            $actions[] = $action;
        }

        return array_values(array_unique($actions));
    }

    public function deleteAll(): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete(ActionLog::class, 'actionLog');

        return $queryBuilder->getQuery()->execute();
    }

    public function deleteByFilters(?DateTimeImmutable $olderThanUtc, ?string $level): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete(ActionLog::class, 'actionLog');

        if ($olderThanUtc !== null) {
            $queryBuilder
                ->andWhere('actionLog.createdAt < :olderThanUtc')
                ->setParameter('olderThanUtc', $olderThanUtc);
        }

        if ($level !== null) {
            $queryBuilder
                ->andWhere('actionLog.level = :level')
                ->setParameter('level', $level);
        }

        return $queryBuilder->getQuery()->execute();
    }
}
