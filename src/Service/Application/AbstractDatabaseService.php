<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Application;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use Throwable;

abstract class AbstractDatabaseService
{
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
    }

    protected function runInTransaction(callable $operation): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $operation();
            $this->entityManager->flush();
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    protected function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    protected function remove(object $entity): void
    {
        $this->entityManager->remove($entity);
    }

    protected function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * The pre-flush uniqueness checks are check-then-act, so a concurrent writer can
     * still win the race. Without this the caller gets a raw SQLSTATE message, which
     * the controllers render straight into the form.
     */
    protected function flushExpectingUnique(string $entityName, string $fieldName, string $value): void
    {
        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw EntityAlreadyExists::forField($entityName, $fieldName, $value);
        }
    }
}
