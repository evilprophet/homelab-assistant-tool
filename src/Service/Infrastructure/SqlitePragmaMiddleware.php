<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Infrastructure;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

class SqlitePragmaMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new SqlitePragmaDriver($driver);
    }
}
