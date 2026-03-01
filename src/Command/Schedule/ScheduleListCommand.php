<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Schedule;

use EvilStudio\HAT\Service\Runtime\ScheduleRuntimeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:schedule:list', description: 'List schedules')]
class ScheduleListCommand extends Command
{
    public function __construct(
        protected ScheduleRuntimeService $scheduleRuntimeService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $runtimeSchedules = $this->scheduleRuntimeService->listRuntimeSchedules();
        if (empty($runtimeSchedules)) {
            $io->note('No schedules found.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($runtimeSchedules as $runtimeSchedule) {
            $rows[] = $runtimeSchedule->toArray();
        }

        $io->table(['ID', 'Name', 'Enabled', 'Cron Expression', 'Command', 'Devices'], $rows);

        return Command::SUCCESS;
    }
}
