<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command;

use EvilStudio\HAT\Provider\DeviceProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:device:show-all', description: 'Show list of all devices')]
class ShowDevicesCommand extends Command
{
    public function __construct(
        protected DeviceProvider $deviceProvider
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('with-status', null, InputOption::VALUE_NONE, 'Show with status (offline/online)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputHelper = new SymfonyStyle($input, $output);

        $withStatus = $input->getOption('with-status');

        if ($withStatus) {
            $this->deviceProvider->checkStatus();
        }

        $headers = $this->deviceProvider->getProperties();
        $deviceProvider = $this->deviceProvider->getDevices();
        $deviceArray = array_map(fn($device) => $device->toArray(), $deviceProvider);

        $outputHelper->table($headers, $deviceArray);

        return Command::SUCCESS;
    }
}
