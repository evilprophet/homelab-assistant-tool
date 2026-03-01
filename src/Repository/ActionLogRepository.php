<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\ActionLog;

class ActionLogRepository
{
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
    }

    public function findAll(): array
    {
        $actionLogs = $this->entityManager->getRepository(ActionLog::class)->findBy([], ['id' => 'ASC']);

        return array_values(array_filter($actionLogs, static fn ($actionLog) => $actionLog instanceof ActionLog));
    }

    public function findByFilters(
        ?string $source = null,
        ?string $level = null,
        ?string $action = null,
        ?int $limit = null
    ): array {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('actionLog')
            ->from(ActionLog::class, 'actionLog')
            ->orderBy('actionLog.id', 'ASC');

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

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

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
                ->andWhere('LOWER(actionLog.message) LIKE :entityText OR LOWER(actionLog.action) LIKE :entityText')
                ->setParameter('entityText', sprintf('%%%s%%', mb_strtolower($entityText)));
        }

        $countQueryBuilder = clone $queryBuilder;
        $total = (int)$countQueryBuilder
            ->select('COUNT(actionLog.id)')
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
