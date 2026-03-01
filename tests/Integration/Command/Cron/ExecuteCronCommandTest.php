<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Cron;

use EvilStudio\HAT\Command\Cron\ExecuteCronCommand;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Runtime\Cron;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ExecuteCronCommandTest extends TestCase
{
    public function testExecuteSkipsWhenCronIsDisabled(): void
    {
        $cron = $this->createMock(Cron::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $configuration->expects($this->once())->method('isCronEnabled')->willReturn(false);
        $cron->expects($this->never())->method('execute');
        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'cron.execute',
                ActionLog::LEVEL_WARNING,
                'Cron execution skipped because cron mode is disabled.'
            );

        $tester = new CommandTester(new ExecuteCronCommand($cron, $configuration, $actionLogService));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Cron is disabled in configuration.', $tester->getDisplay());
    }

    public function testExecuteRunsCronAndAutomaticCleanup(): void
    {
        $cron = $this->createMock(Cron::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $configuration->expects($this->once())->method('isCronEnabled')->willReturn(true);
        $cron->expects($this->once())->method('execute');
        $actionLogService->expects($this->once())
            ->method('cleanupOlderThanDays')
            ->with(ActionLogService::DEFAULT_RETENTION_DAYS)
            ->willReturn(4);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'logs.cleanup',
                ActionLog::LEVEL_INFO,
                'Automatic cleanup removed 4 action logs older than 90 days.'
            );

        $tester = new CommandTester(new ExecuteCronCommand($cron, $configuration, $actionLogService));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteReturnsFailureWhenCronThrowsException(): void
    {
        $cron = $this->createMock(Cron::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $configuration->expects($this->once())->method('isCronEnabled')->willReturn(true);
        $cron->expects($this->once())->method('execute')->willThrowException(new RuntimeException('Cron failed.'));
        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'cron.execute',
                ActionLog::LEVEL_ERROR,
                'Cron execution failed: Cron failed.'
            );
        $actionLogService->expects($this->never())->method('cleanupOlderThanDays');

        $tester = new CommandTester(new ExecuteCronCommand($cron, $configuration, $actionLogService));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Cron failed.', $tester->getDisplay());
    }
}
