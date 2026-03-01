<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Logs;

use DateTimeImmutable;
use DateTimeZone;
use EvilStudio\HAT\Command\Logs\LogsListCommand;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class LogsListCommandTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteReturnsFailureForInvalidLimit(): void
    {
        $actionLogService = $this->createMock(ActionLogService::class);
        $actionLogService->expects($this->never())->method('listActionLogs');

        $tester = new CommandTester(new LogsListCommand($actionLogService));
        $exitCode = $tester->execute(['--limit' => '0'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('--limit must be a positive integer.', $tester->getDisplay());
    }

    public function testExecuteShowsTableRowsWhenLogsExist(): void
    {
        $actionLogService = $this->createMock(ActionLogService::class);
        $actionLog = (new ActionLog())
            ->setCreatedAt(new DateTimeImmutable('2026-02-28 10:00:00', new DateTimeZone('UTC')))
            ->setSource(ActionLog::SOURCE_CLI)
            ->setLevel(ActionLog::LEVEL_INFO)
            ->setAction('device.start')
            ->setMessage('Started device node-1');
        $this->setEntityId($actionLog, 7);

        $actionLogService->expects($this->once())
            ->method('listActionLogs')
            ->with(null, null, null, 5)
            ->willReturn([$actionLog]);

        $tester = new CommandTester(new LogsListCommand($actionLogService));
        $exitCode = $tester->execute(['--limit' => '5'], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('device.start', $tester->getDisplay());
        $this->assertStringContainsString('Started device node-1', $tester->getDisplay());
    }
}
