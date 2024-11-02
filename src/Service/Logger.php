<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as MonologLogger;

class Logger
{
    protected const string LOG_FILE = 'cron.log';
    protected MonologLogger $logger;

    public function __construct(string $logDirectory)
    {
        $logFilePath = sprintf('%s/%s', $logDirectory, self::LOG_FILE);

        $this->logger = new MonologLogger('cron');
        $this->logger->pushHandler(new StreamHandler($logFilePath, Level::Info));
    }

    public function logInfo(string $message): void
    {
        $this->logger->info($message);
    }

    public function logError(string $message): void
    {
        $this->logger->error($message);
    }
}
