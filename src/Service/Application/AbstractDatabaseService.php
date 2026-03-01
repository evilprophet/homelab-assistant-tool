<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Application;

use Doctrine\ORM\EntityManagerInterface;
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
}
