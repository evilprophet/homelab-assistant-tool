<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Cron;

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
    use \EvilStudio\HAT\Tests\Support\TemporaryPathTrait;

    protected function tearDown(): void
    {
        $this->removeTemporaryPaths();

        parent::tearDown();
    }
    public function testExecuteSkipsWhenCronIsDisabled(): void
    {
        $cron = $this->createMock(Cron::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $configuration->expects($this->once())->method('isCronEnabled')->willReturn(false);
        $cron->expects($this->never())->method('execute');
        $actionLogService->expects($this->never())->method('createActionLog');

        $tester = new CommandTester(
            new ExecuteCronCommand($cron, $configuration, $actionLogService, $this->createLockDirectory())
        );
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
        $configuration->expects($this->exactly(2))->method('getActionLogRetentionDays')->willReturn(90);
        $cron->expects($this->once())->method('execute');
        $actionLogService->expects($this->once())
            ->method('cleanupOlderThanDays')
            ->with(90)
            ->willReturn(4);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'logs.cleanup',
                ActionLog::LEVEL_INFO,
                'Automatic cleanup removed 4 action logs older than 90 days.'
            );

        $tester = new CommandTester(
            new ExecuteCronCommand($cron, $configuration, $actionLogService, $this->createLockDirectory())
        );
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

        $tester = new CommandTester(
            new ExecuteCronCommand($cron, $configuration, $actionLogService, $this->createLockDirectory())
        );
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Cron failed.', $tester->getDisplay());
    }

    public function testExecuteSkipsWhenAnotherRunHoldsTheLock(): void
    {
        $applicationDirectory = $this->createLockDirectory();
        $cron = $this->createMock(Cron::class);
        $configuration = $this->createMock(Configuration::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $configuration->expects($this->once())->method('isCronEnabled')->willReturn(true);
        $cron->expects($this->never())->method('execute');
        $actionLogService->expects($this->never())->method('cleanupOlderThanDays');
        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'cron.execute',
                ActionLog::LEVEL_WARNING,
                'Cron execution skipped because a previous run is still in progress.'
            );

        $heldLock = fopen($applicationDirectory . '/var/data/hat-cron.lock', 'c');
        flock($heldLock, LOCK_EX | LOCK_NB);

        $tester = new CommandTester(
            new ExecuteCronCommand($cron, $configuration, $actionLogService, $applicationDirectory)
        );
        $exitCode = $tester->execute([], ['interactive' => false]);

        flock($heldLock, LOCK_UN);
        fclose($heldLock);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Another cron run is still in progress.', $tester->getDisplay());
    }

    protected function createLockDirectory(): string
    {
        $applicationDirectory = $this->createTemporaryPath('hat-cron-lock-');
        mkdir($applicationDirectory . '/var/data', 0777, true);

        return $applicationDirectory;
    }
}
