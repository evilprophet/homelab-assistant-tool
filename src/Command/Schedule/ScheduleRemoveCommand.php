<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Schedule;

use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'hat:schedule:remove', description: 'Remove schedule')]
class ScheduleRemoveCommand extends Command
{
    public function __construct(
        protected ScheduleService $scheduleService,
        protected ActionLogService $actionLogService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'Schedule ID')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $scheduleId = $this->resolveScheduleId($input, $io);
        if ($scheduleId === null) {
            return Command::FAILURE;
        }

        try {
            $schedule = $this->scheduleService->getScheduleById($scheduleId);
        } catch (EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $linkedDeviceNames = [];
        foreach ($schedule->getDevices()->toArray() as $device) {
            if ($device instanceof Device) {
                $linkedDeviceNames[] = $device->getName();
            }
        }

        if (!empty($linkedDeviceNames)) {
            $io->warning(
                sprintf(
                    "Schedule '%s' is linked to devices: %s. Links will be removed.",
                    $schedule->getName(),
                    implode(', ', $linkedDeviceNames)
                )
            );
        }

        if (!$input->getOption('force')) {
            if (!$io->confirm(sprintf("Remove schedule '%s'?", $schedule->getName()), false)) {
                $io->warning('Schedule removal aborted by user.');

                return Command::SUCCESS;
            }
        }

        try {
            $this->scheduleService->removeSchedule($scheduleId);
        } catch (EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::SCHEDULE_REMOVE->value,
            ActionLog::LEVEL_WARNING,
            sprintf(
                "Schedule '%s' removed. Detached devices: %d.",
                $schedule->getName(),
                count($linkedDeviceNames)
            )
        );

        $io->success(sprintf("Schedule '%s' removed.", $schedule->getName()));

        return Command::SUCCESS;
    }

    protected function resolveScheduleId(InputInterface $input, SymfonyStyle $io): ?int
    {
        $idArgument = $input->getArgument('id');
        if ($idArgument !== null) {
            $normalized = filter_var($idArgument, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($normalized === false) {
                $io->error('id must be a positive integer.');

                return null;
            }

            return (int)$normalized;
        }

        $schedules = $this->scheduleService->listSchedules();
        if (empty($schedules)) {
            $io->error('No schedules available to remove.');

            return null;
        }

        $choices = [];
        foreach ($schedules as $schedule) {
            $scheduleId = $schedule->getId();
            if ($scheduleId === null) {
                continue;
            }

            $label = sprintf('%d: %s: %s', $scheduleId, $schedule->getName(), $schedule->isEnabled() ? 'yes' : 'no');
            $choices[$label] = $scheduleId;
        }

        if (empty($choices)) {
            $io->error('No removable schedules found.');

            return null;
        }

        $selected = $io->choice('Select schedule to remove', array_keys($choices));

        return $choices[$selected];
    }
}
