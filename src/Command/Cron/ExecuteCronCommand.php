<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Cron;

use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Runtime\Cron;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:cron:execute', description: 'Execute cron jobs')]
class ExecuteCronCommand extends Command
{
    public function __construct(
        protected Cron $cron,
        protected Configuration $configuration,
        protected ActionLogService $actionLogService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputHelper = new SymfonyStyle($input, $output);

        if (!$this->configuration->isCronEnabled()) {
            $outputHelper->warning('Cron is disabled in configuration.');
            $this->actionLogService->createActionLog(
                ActionLog::SOURCE_CLI,
                ActionLogAction::CRON_EXECUTE->value,
                ActionLog::LEVEL_WARNING,
                'Cron execution skipped because cron mode is disabled.'
            );

            return Command::SUCCESS;
        }

        try {
            $this->cron->execute();

            $removedActionLogsCount = $this->actionLogService->cleanupOlderThanDays(
                ActionLogService::DEFAULT_RETENTION_DAYS
            );
            if ($removedActionLogsCount > 0) {
                $this->actionLogService->createActionLog(
                    ActionLog::SOURCE_CLI,
                    ActionLogAction::LOGS_CLEANUP->value,
                    ActionLog::LEVEL_INFO,
                    sprintf(
                        'Automatic cleanup removed %d action logs older than %d days.',
                        $removedActionLogsCount,
                        ActionLogService::DEFAULT_RETENTION_DAYS
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
            $outputHelper->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
