<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Schedule;

use Cron\CronExpression;
use EvilStudio\HAT\Command\Support\BooleanOptionTrait;
use EvilStudio\HAT\Command\Support\InteractiveInputTrait;
use EvilStudio\HAT\Command\Support\ScheduleDeviceSelectionTrait;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'hat:schedule:create', description: 'Create schedule')]
class ScheduleCreateCommand extends Command
{
    use BooleanOptionTrait;
    use InteractiveInputTrait;
    use ScheduleDeviceSelectionTrait;

    protected const array ALLOWED_COMMANDS = [
        ScheduleInterface::COMMAND_START,
        ScheduleInterface::COMMAND_STOP,
    ];

    public function __construct(
        protected ScheduleService $scheduleService,
        protected DeviceService $deviceService,
        protected ActionLogService $actionLogService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Schedule name')
            ->addArgument('cron-expression', InputArgument::OPTIONAL, 'Cron expression')
            ->addArgument('schedule-command', InputArgument::OPTIONAL, 'Schedule command')
            ->addOption(
                'device-id',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Device ID (repeatable)'
            )
            ->addOption('is-enabled', null, InputOption::VALUE_REQUIRED, 'Schedule enabled (1/0)', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $this->resolveRequiredArgument($input, $io, 'name', 'Schedule name');
        if ($name === null) {
            return Command::FAILURE;
        }

        $cronExpression = $this->resolveCronExpression($input, $io, 'cron-expression', null);
        if ($cronExpression === null) {
            return Command::FAILURE;
        }

        $command = $this->resolveScheduleCommand($input, $io, 'schedule-command', ScheduleInterface::COMMAND_START);
        if ($command === null) {
            return Command::FAILURE;
        }

        $deviceIds = $this->parseDeviceIds($input->getOption('device-id'), $io);
        if ($deviceIds === null) {
            return Command::FAILURE;
        }

        if (empty($deviceIds) && !$input->hasParameterOption('--device-id') && $input->isInteractive()) {
            $deviceIds = $this->promptDeviceIds($io, [], 'Schedule devices (optional)');
            if ($deviceIds === null) {
                return Command::FAILURE;
            }
        }

        $isEnabled = $this->resolveIsEnabledForCreate($input, $io);
        if ($isEnabled === null) {
            return Command::FAILURE;
        }

        try {
            $schedule = $this->scheduleService->createSchedule(
                $name,
                $cronExpression,
                $command,
                $deviceIds,
                $isEnabled
            );
        } catch (EntityAlreadyExists | EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Schedule '%s' created with ID %d.", $schedule->getName(), (int)$schedule->getId()));

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::SCHEDULE_CREATE->value,
            ActionLog::LEVEL_INFO,
            sprintf("Schedule '%s' created with ID %d.", $schedule->getName(), (int)$schedule->getId())
        );

        return Command::SUCCESS;
    }

    protected function resolveCronExpression(
        InputInterface $input,
        SymfonyStyle $io,
        string $argumentName,
        ?string $defaultValue
    ): ?string {
        $argument = $input->getArgument($argumentName);
        $value = is_string($argument) && trim($argument) !== '' ? trim($argument) : null;

        if ($value === null && $input->isInteractive()) {
            $value = trim((string)$io->ask('Cron expression', $defaultValue ?? '* * * * *'));
        }

        if ($value === null || $value === '') {
            $io->error(sprintf("Argument '%s' is required.", $argumentName));

            return null;
        }

        if (!CronExpression::isValidExpression($value)) {
            $io->error(sprintf("Invalid cron expression '%s'.", $value));

            return null;
        }

        return $value;
    }

    protected function resolveScheduleCommand(
        InputInterface $input,
        SymfonyStyle $io,
        string $argumentName,
        string $defaultValue
    ): ?string {
        $argument = $input->getArgument($argumentName);
        $command = is_string($argument) && trim($argument) !== '' ? trim($argument) : null;

        if ($command === null && $input->isInteractive()) {
            $command = (string)$io->choice('Schedule command', self::ALLOWED_COMMANDS, $defaultValue);
        }

        if ($command === null || $command === '') {
            $io->error(sprintf("Argument '%s' is required.", $argumentName));

            return null;
        }

        if (!in_array($command, self::ALLOWED_COMMANDS, true)) {
            $io->error(
                sprintf("Invalid command '%s'. Allowed values: %s.", $command, implode(', ', self::ALLOWED_COMMANDS))
            );

            return null;
        }

        return $command;
    }

    protected function resolveIsEnabledForCreate(InputInterface $input, SymfonyStyle $io): ?bool
    {
        $rawValue = $input->getOption('is-enabled');
        if ($input->hasParameterOption('--is-enabled')) {
            return $this->parseBoolOption($rawValue, '--is-enabled', $io);
        }

        if ($input->isInteractive()) {
            return $io->confirm('Schedule enabled?');
        }

        return $this->parseBoolOption($rawValue, '--is-enabled', $io);
    }
}
