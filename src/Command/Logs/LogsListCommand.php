<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Logs;

use DateTimeZone;
use EvilStudio\HAT\Service\Application\ActionLogService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'hat:logs:list', description: 'List logs')]
class LogsListCommand extends Command
{
    protected const int MESSAGE_PREVIEW_LENGTH = 160;

    public function __construct(
        protected ActionLogService $actionLogService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Log row limit',
                (string)ActionLogService::DEFAULT_LIST_LIMIT
            )
            ->addOption(
                'source',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf('Log source (%s)', implode('|', ActionLogService::getAllowedSources()))
            )
            ->addOption(
                'level',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf('Log level (%s)', implode('|', ActionLogService::getAllowedLevels()))
            )
            ->addOption('action', null, InputOption::VALUE_REQUIRED, 'Log action');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false) {
            $io->error('--limit must be a positive integer.');

            return Command::FAILURE;
        }

        $source = $this->nullIfEmpty($input->getOption('source'));
        $level = $this->nullIfEmpty($input->getOption('level'));
        $action = $this->nullIfEmpty($input->getOption('action'));

        try {
            $actionLogs = $this->actionLogService->listActionLogs($source, $level, $action, (int)$limit);
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        if (empty($actionLogs)) {
            $io->note('No action logs found.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($actionLogs as $actionLog) {
            $rows[] = [
                $actionLog->getId(),
                $actionLog->getCreatedAt()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                $actionLog->getSource(),
                $actionLog->getLevel(),
                $actionLog->getAction(),
                $this->formatMessagePreview($actionLog->getMessage()),
            ];
        }

        $io->table(['ID', 'Created At (UTC)', 'Source', 'Level', 'Action', 'Message'], $rows);

        return Command::SUCCESS;
    }

    protected function nullIfEmpty(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    protected function formatMessagePreview(string $message): string
    {
        $singleLineMessage = str_replace(["\r", "\n"], ' ', $message);
        if (mb_strlen($singleLineMessage) <= self::MESSAGE_PREVIEW_LENGTH) {
            return $singleLineMessage;
        }

        return mb_substr($singleLineMessage, 0, self::MESSAGE_PREVIEW_LENGTH - 3) . '...';
    }
}
