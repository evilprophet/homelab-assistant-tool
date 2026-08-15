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
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Entity\Schedule;
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

#[AsCommand(name: 'hat:schedule:update', description: 'Update schedule')]
class ScheduleUpdateCommand extends Command
{
    use BooleanOptionTrait;
    use InteractiveInputTrait;
    use ScheduleDeviceSelectionTrait;

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
            ->addArgument('id', InputArgument::OPTIONAL, 'Schedule ID')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Schedule name')
            ->addOption('is-enabled', null, InputOption::VALUE_REQUIRED, 'Schedule enabled (1/0)')
            ->addOption('cron-expression', null, InputOption::VALUE_REQUIRED, 'Cron expression')
            ->addOption('command', null, InputOption::VALUE_REQUIRED, 'Schedule command')
            ->addOption(
                'device-id',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Device ID (repeatable)'
            )
            ->addOption('clear-devices', null, InputOption::VALUE_NONE, 'Clear schedule devices');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('clear-devices') && !empty($input->getOption('device-id'))) {
            $io->error('Use either --device-id or --clear-devices, not both.');

            return Command::FAILURE;
        }

        $scheduleId = $this->resolveScheduleId($input, $io);
        if ($scheduleId === false) {
            return Command::FAILURE;
        }

        try {
            $schedule = $this->scheduleService->getScheduleById($scheduleId);
        } catch (EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $name = $this->resolveStringOption($input, $io, 'name', 'Schedule name', $schedule->getName());
        if ($name === null) {
            return Command::FAILURE;
        }

        $isEnabled = $this->resolveIsEnabledForUpdate($input, $io, $schedule->isEnabled());
        if ($isEnabled === null) {
            return Command::FAILURE;
        }

        $cronExpression = $this->resolveCronExpression(
            $input,
            $io,
            'cron-expression',
            $schedule->getCronExpression()
        );
        if ($cronExpression === null) {
            return Command::FAILURE;
        }

        $command = $this->resolveScheduleCommand($input, $io, 'command', $schedule->getCommand());
        if ($command === null) {
            return Command::FAILURE;
        }

        if ($input->getOption('clear-devices')) {
            $deviceIds = [];
        } elseif ($input->hasParameterOption('--device-id')) {
            $deviceIds = $this->parseDeviceIds($input->getOption('device-id'), $io);
            if ($deviceIds === null) {
                return Command::FAILURE;
            }
        } elseif ($input->isInteractive()) {
            $deviceIds = $this->promptDeviceIds($io, $this->resolveCurrentScheduleDeviceIds($schedule));
            if ($deviceIds === null) {
                return Command::FAILURE;
            }
        } else {
            $deviceIds = $this->resolveCurrentScheduleDeviceIds($schedule);
        }

        try {
            $updatedSchedule = $this->scheduleService->updateSchedule(
                $scheduleId,
                $name,
                $isEnabled,
                $cronExpression,
                $command,
                $deviceIds
            );
        } catch (EntityAlreadyExists | EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Schedule '%s' updated.", $updatedSchedule->getName()));

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::SCHEDULE_UPDATE->value,
            ActionLog::LEVEL_INFO,
            sprintf("Schedule '%s' updated.", $updatedSchedule->getName())
        );

        return Command::SUCCESS;
    }

    protected function resolveScheduleId(InputInterface $input, SymfonyStyle $io): int|false
    {
        $idArgument = $input->getArgument('id');
        if (is_string($idArgument) && trim($idArgument) !== '') {
            return $this->parsePositiveInt($idArgument, 'id', $io);
        }

        if (!$input->isInteractive()) {
            $io->error("Argument 'id' is required.");

            return false;
        }

        $schedules = $this->scheduleService->listSchedules();
        if (empty($schedules)) {
            $io->error('No schedules available to update.');

            return false;
        }

        $choices = [];
        $mapping = [];
        foreach ($schedules as $schedule) {
            $resolvedScheduleId = $schedule->getId();
            if ($resolvedScheduleId === null) {
                continue;
            }

            $label = sprintf('%d: %s', $resolvedScheduleId, $schedule->getName());
            $choices[] = $label;
            $mapping[$label] = $resolvedScheduleId;
        }

        $selected = $io->choice('Schedule', $choices);

        return (int)$mapping[$selected];
    }

    protected function resolveCronExpression(
        InputInterface $input,
        SymfonyStyle $io,
        string $optionName,
        string $defaultValue
    ): ?string {
        $value = $this->resolveStringOption($input, $io, $optionName, 'Cron expression', $defaultValue);
        if ($value === null) {
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
        string $optionName,
        string $defaultValue
    ): ?string {
        if ($input->hasParameterOption(sprintf('--%s', $optionName))) {
            $command = trim((string)$input->getOption($optionName));
        } elseif ($input->isInteractive()) {
            $default = in_array($defaultValue, ScheduleInterface::COMMANDS, true)
                ? $defaultValue
                : ScheduleInterface::COMMAND_START;
            $command = (string)$io->choice('Schedule command', ScheduleInterface::COMMANDS, $default);
        } else {
            $command = $defaultValue;
        }

        if (!in_array($command, ScheduleInterface::COMMANDS, true)) {
            $io->error(
                sprintf(
                    "Invalid command '%s'. Allowed values: %s.",
                    $command,
                    implode(', ', ScheduleInterface::COMMANDS)
                )
            );

            return null;
        }

        return $command;
    }

    protected function resolveCurrentScheduleDeviceIds(Schedule $schedule): array
    {
        $deviceIds = [];
        foreach ($schedule->getDevices()->toArray() as $device) {
            if (!$device instanceof Device || $device->getId() === null) {
                continue;
            }

            $deviceIds[] = $device->getId();
        }

        return array_values(array_unique($deviceIds));
    }

    protected function resolveIsEnabledForUpdate(
        InputInterface $input,
        SymfonyStyle $io,
        bool $defaultValue
    ): ?bool {
        if ($input->hasParameterOption('--is-enabled')) {
            return $this->parseBoolOption($input->getOption('is-enabled'), '--is-enabled', $io);
        }

        if ($input->isInteractive()) {
            return $io->confirm('Schedule enabled?', $defaultValue);
        }

        return $defaultValue;
    }
}
