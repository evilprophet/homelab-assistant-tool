<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service;

use EvilStudio\HAT\Service\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    protected string $testLogDirectory;
    protected string $testLogFile;

    protected function setUp(): void
    {
        $this->testLogDirectory = sys_get_temp_dir() . '/hat_test_logs_' . uniqid();
        $this->testLogFile = $this->testLogDirectory . '/cron.log';

        if (!is_dir($this->testLogDirectory)) {
            mkdir($this->testLogDirectory, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
        if (is_dir($this->testLogDirectory)) {
            rmdir($this->testLogDirectory);
        }
    }

    public function testConstructorCreatesLogger(): void
    {
        $logger = new Logger($this->testLogDirectory);

        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testLogInfoWritesToFile(): void
    {
        $logger = new Logger($this->testLogDirectory);
        $logger->logInfo('Test info message');

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);

        $this->assertStringContainsString('Test info message', $logContent);
        $this->assertStringContainsString('INFO', $logContent);
    }

    public function testLogWarningWritesToFile(): void
    {
        $logger = new Logger($this->testLogDirectory);
        $logger->logWarning('Test warning message');

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);

        $this->assertStringContainsString('Test warning message', $logContent);
        $this->assertStringContainsString('WARNING', $logContent);
    }

    public function testLogErrorWritesToFile(): void
    {
        $logger = new Logger($this->testLogDirectory);
        $logger->logError('Test error message');

        $this->assertFileExists($this->testLogFile);
        $logContent = file_get_contents($this->testLogFile);

        $this->assertStringContainsString('Test error message', $logContent);
        $this->assertStringContainsString('ERROR', $logContent);
    }

    public function testMultipleLogEntries(): void
    {
        $logger = new Logger($this->testLogDirectory);

        $logger->logInfo('First message');
        $logger->logWarning('Second message');
        $logger->logError('Third message');

        $logContent = file_get_contents($this->testLogFile);

        $this->assertStringContainsString('First message', $logContent);
        $this->assertStringContainsString('Second message', $logContent);
        $this->assertStringContainsString('Third message', $logContent);
    }

    public function testLogFileNameIsCorrect(): void
    {
        $logger = new Logger($this->testLogDirectory);
        $logger->logInfo('Test');

        $expectedPath = $this->testLogDirectory . '/cron.log';
        $this->assertFileExists($expectedPath);
    }

    public function testLogContainsCronChannel(): void
    {
        $logger = new Logger($this->testLogDirectory);
        $logger->logInfo('Test message');

        $logContent = file_get_contents($this->testLogFile);

        $this->assertStringContainsString('cron', $logContent);
    }
}
