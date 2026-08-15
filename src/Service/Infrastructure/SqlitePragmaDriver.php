<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Infrastructure;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

class SqlitePragmaDriver extends AbstractDriverMiddleware
{
    // pdo_sqlite disables foreign keys on every connection. busy_timeout is left alone
    // because its 60s default is higher than anything worth setting here.
    protected const array PRAGMAS = [
        'PRAGMA foreign_keys = ON',
        'PRAGMA journal_mode = WAL',
    ];

    public function connect(
        #[SensitiveParameter]
        array $params,
    ): DriverConnection {
        $connection = parent::connect($params);

        foreach (self::PRAGMAS as $pragma) {
            $connection->exec($pragma);
        }

        return $connection;
    }
}
