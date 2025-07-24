<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Schedule;

use EvilStudio\HAT\Provider\ScheduleProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:schedule:show-all', description: 'Show list of all schedules')]
class ShowScheduleCommand extends Command
{
    public function __construct(
        protected ScheduleProvider $scheduleProvider
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputHelper = new SymfonyStyle($input, $output);

        $headers = $this->scheduleProvider->getProperties();
        $scheduleList = $this->scheduleProvider->getScheduleList();
        $scheduleArray = array_map(fn($schedule) => $schedule->toArray(), $scheduleList);

        $outputHelper->table($headers, $scheduleArray);

        return Command::SUCCESS;
    }
}
