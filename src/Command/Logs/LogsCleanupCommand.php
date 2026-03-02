<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Logs;

use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Application\ActionLogService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'hat:logs:cleanup', description: 'Clean logs')]
class LogsCleanupCommand extends Command
{
    protected const string ALL_LOGS_CONFIRMATION = 'This will permanently remove all action logs. Continue?';

    public function __construct(
        protected ActionLogService $actionLogService,
        protected Configuration $configuration
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Retention in days',
                (string)$this->configuration->getActionLogRetentionDays()
            )
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Remove all action logs')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation for --all');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $removeAll = (bool)$input->getOption('all');

        if ($removeAll) {
            if ($input->hasParameterOption('--days')) {
                $io->error('Use either --all or --days, not both.');

                return Command::FAILURE;
            }

            if (!$input->getOption('force')) {
                if (!$io->confirm(self::ALL_LOGS_CONFIRMATION, false)) {
                    $io->warning('Action logs cleanup aborted by user.');

                    return Command::SUCCESS;
                }
            }

            try {
                $removedActionLogsCount = $this->actionLogService->cleanupAll();
            } catch (Throwable $exception) {
                $io->error($exception->getMessage());

                return Command::FAILURE;
            }

            $io->success(sprintf('Removed all action logs (%d rows).', $removedActionLogsCount));

            return Command::SUCCESS;
        }

        $days = filter_var($input->getOption('days'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($days === false) {
            $io->error('--days must be a positive integer.');

            return Command::FAILURE;
        }

        try {
            $removedActionLogsCount = $this->actionLogService->cleanupOlderThanDays((int)$days);
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::LOGS_CLEANUP->value,
            ActionLog::LEVEL_INFO,
            sprintf(
                'Manual cleanup removed %d action logs older than %d days.',
                $removedActionLogsCount,
                (int)$days
            )
        );

        $io->success(
            sprintf(
                'Removed %d action logs older than %d days.',
                $removedActionLogsCount,
                (int)$days
            )
        );

        return Command::SUCCESS;
    }
}
