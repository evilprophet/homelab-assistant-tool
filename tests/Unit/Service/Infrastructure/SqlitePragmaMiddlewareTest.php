<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Infrastructure;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use EvilStudio\HAT\Service\Infrastructure\SqlitePragmaDriver;
use EvilStudio\HAT\Service\Infrastructure\SqlitePragmaMiddleware;
use PHPUnit\Framework\TestCase;

class SqlitePragmaMiddlewareTest extends TestCase
{
    public function testWrapsTheDriver(): void
    {
        $wrapped = (new SqlitePragmaMiddleware())->wrap($this->createMock(Driver::class));

        $this->assertInstanceOf(SqlitePragmaDriver::class, $wrapped);
    }

    public function testEnablesForeignKeysAndWalOnEveryConnection(): void
    {
        $executedStatements = [];
        $connection = $this->createStub(DriverConnection::class);
        $connection->method('exec')->willReturnCallback(
            function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;

                return 0;
            }
        );

        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())->method('connect')->willReturn($connection);

        (new SqlitePragmaMiddleware())->wrap($driver)->connect(['path' => ':memory:']);

        $this->assertSame(
            ['PRAGMA foreign_keys = ON', 'PRAGMA journal_mode = WAL'],
            $executedStatements
        );
    }

    public function testDoesNotLowerTheBusyTimeout(): void
    {
        $executedStatements = [];
        $connection = $this->createStub(DriverConnection::class);
        $connection->method('exec')->willReturnCallback(
            function (string $sql) use (&$executedStatements): int {
                $executedStatements[] = $sql;

                return 0;
            }
        );

        $driver = $this->createMock(Driver::class);
        $driver->method('connect')->willReturn($connection);

        (new SqlitePragmaMiddleware())->wrap($driver)->connect(['path' => ':memory:']);

        foreach ($executedStatements as $statement) {
            $this->assertStringNotContainsStringIgnoringCase('busy_timeout', $statement);
        }
    }
}
