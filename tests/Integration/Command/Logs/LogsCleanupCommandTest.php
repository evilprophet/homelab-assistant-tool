<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Logs;

use EvilStudio\HAT\Command\Logs\LogsCleanupCommand;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class LogsCleanupCommandTest extends TestCase
{
    public function testExecuteCleansByDaysAndCreatesActionLog(): void
    {
        $actionLogService = $this->createMock(ActionLogService::class);

        $actionLogService->expects($this->once())->method('cleanupOlderThanDays')->with(30)->willReturn(12);
        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'logs.cleanup',
                ActionLog::LEVEL_INFO,
                'Manual cleanup removed 12 action logs older than 30 days.'
            );
        $actionLogService->expects($this->never())->method('cleanupAll');

        $tester = new CommandTester(new LogsCleanupCommand($actionLogService));
        $exitCode = $tester->execute(['--days' => '30'], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Removed 12 action logs older than 30 days.', $tester->getDisplay());
    }

    public function testExecuteCleansAllWhenForced(): void
    {
        $actionLogService = $this->createMock(ActionLogService::class);

        $actionLogService->expects($this->once())->method('cleanupAll')->with()->willReturn(15);
        $actionLogService->expects($this->never())->method('cleanupOlderThanDays');
        $actionLogService->expects($this->never())->method('createActionLog');

        $tester = new CommandTester(new LogsCleanupCommand($actionLogService));
        $exitCode = $tester->execute(['--all' => true, '--force' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Removed all action logs (15 rows).', $tester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenAllAndDaysAreUsedTogether(): void
    {
        $actionLogService = $this->createMock(ActionLogService::class);
        $actionLogService->expects($this->never())->method('cleanupAll');
        $actionLogService->expects($this->never())->method('cleanupOlderThanDays');

        $tester = new CommandTester(new LogsCleanupCommand($actionLogService));
        $exitCode = $tester->execute(['--all' => true, '--days' => '30'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Use either --all or --days, not both.', $tester->getDisplay());
    }
}
