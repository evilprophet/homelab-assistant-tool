<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Application;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Service\Application\AbstractDatabaseService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AbstractDatabaseServiceTest extends TestCase
{
    public function testRunInTransactionFlushesAndCommitsOnSuccess(): void
    {
        $connection = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = $this->createService($entityManager);

        $entityManager->expects($this->once())->method('getConnection')->willReturn($connection);
        $connection->expects($this->once())->method('beginTransaction');
        $entityManager->expects($this->once())->method('flush');
        $connection->expects($this->once())->method('commit');
        $connection->expects($this->never())->method('rollBack');

        $service->callRunInTransaction(static function (): void {
        });
    }

    public function testRunInTransactionRollsBackAndRethrowsOnFailure(): void
    {
        $connection = $this->createMock(Connection::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = $this->createService($entityManager);

        $entityManager->expects($this->once())->method('getConnection')->willReturn($connection);
        $connection->expects($this->once())->method('beginTransaction');
        $entityManager->expects($this->never())->method('flush');
        $connection->expects($this->once())->method('isTransactionActive')->willReturn(true);
        $connection->expects($this->once())->method('rollBack');
        $connection->expects($this->never())->method('commit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('transaction failed');

        $service->callRunInTransaction(static function (): void {
            throw new RuntimeException('transaction failed');
        });
    }

    public function testPersistRemoveAndFlushDelegateToEntityManager(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = $this->createService($entityManager);
        $entity = new \stdClass();

        $entityManager->expects($this->once())->method('persist')->with($entity);
        $entityManager->expects($this->once())->method('remove')->with($entity);
        $entityManager->expects($this->once())->method('flush');

        $service->callPersist($entity);
        $service->callRemove($entity);
        $service->callFlush();
    }

    protected function createService(EntityManagerInterface $entityManager): object
    {
        return new class ($entityManager) extends AbstractDatabaseService {
            public function callRunInTransaction(callable $operation): void
            {
                $this->runInTransaction($operation);
            }

            public function callPersist(object $entity): void
            {
                $this->persist($entity);
            }

            public function callRemove(object $entity): void
            {
                $this->remove($entity);
            }

            public function callFlush(): void
            {
                $this->flush();
            }
        };
    }
}
