<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Cron;

use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Runtime\Cron;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:cron:execute', description: 'Execute cron jobs')]
class ExecuteCronCommand extends Command
{
    protected const string LOCK_FILE_RELATIVE_PATH = 'var/data/hat-cron.lock';

    public function __construct(
        protected Cron $cron,
        protected Configuration $configuration,
        protected ActionLogService $actionLogService,
        protected LoggerInterface $cronLogger,
        protected string $applicationDirectory
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputHelper = new SymfonyStyle($input, $output);

        if (!$this->configuration->isCronEnabled()) {
            $outputHelper->warning('Cron is disabled in configuration.');
            $this->cronLogger->warning("Cron is disabled in configuration.");

            return Command::SUCCESS;
        }

        $lockHandle = $this->acquireLock();
        if ($lockHandle === null) {
            $outputHelper->warning('Another cron run is still in progress.');
            $this->cronLogger->warning("Another cron run is still in progress.");
            $this->actionLogService->createActionLog(
                ActionLog::SOURCE_CLI,
                ActionLogAction::CRON_EXECUTE->value,
                ActionLog::LEVEL_WARNING,
                'Cron execution skipped because a previous run is still in progress.'
            );

            return Command::SUCCESS;
        }

        try {
            $this->cron->execute();

            $removedActionLogsCount = $this->actionLogService->cleanupOlderThanDays(
                $this->configuration->getActionLogRetentionDays()
            );
            if ($removedActionLogsCount > 0) {
                $this->actionLogService->createActionLog(
                    ActionLog::SOURCE_CLI,
                    ActionLogAction::LOGS_CLEANUP->value,
                    ActionLog::LEVEL_INFO,
                    sprintf(
                        'Automatic cleanup removed %d action logs older than %d days.',
                        $removedActionLogsCount,
                        $this->configuration->getActionLogRetentionDays()
                    )
                );
            }
        } catch (Exception $e) {
            $this->actionLogService->createActionLog(
                ActionLog::SOURCE_CLI,
                ActionLogAction::CRON_EXECUTE->value,
                ActionLog::LEVEL_ERROR,
                sprintf('Cron execution failed: %s', $e->getMessage())
            );
            $this->cronLogger->error(
                'Cron execution failed.',
                ['exception' => $e->getMessage()]
            );
            $outputHelper->error($e->getMessage());
            return Command::FAILURE;
        } finally {
            $this->releaseLock($lockHandle);
        }

        return Command::SUCCESS;
    }

    protected function acquireLock(): mixed
    {
        $lockFilePath = sprintf('%s/%s', rtrim($this->applicationDirectory, '/\\'), self::LOCK_FILE_RELATIVE_PATH);
        $lockHandle = fopen($lockFilePath, 'c');
        if ($lockHandle === false) {
            return null;
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);

            return null;
        }

        return $lockHandle;
    }

    protected function releaseLock(mixed $lockHandle): void
    {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
