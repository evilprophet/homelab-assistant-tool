<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Device;

use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:device:list', description: 'List devices')]
class DeviceListCommand extends Command
{
    public function __construct(
        protected DeviceOperationsService $deviceOperationsService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('with-status', null, InputOption::VALUE_NONE, 'Include runtime status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $withStatus = (bool)$input->getOption('with-status');
        $runtimeDevices = $this->deviceOperationsService->listDevices($withStatus);

        if (empty($runtimeDevices)) {
            $io->note('No devices found.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($runtimeDevices as $runtimeDevice) {
            $row = $runtimeDevice->toArray();
            unset($row['platform_key']);
            $rows[] = $row;
        }

        $headers = ['ID', 'Name', 'IP', 'MAC', 'Platform', 'UPS', 'UPS Low Runtime Threshold', 'Auto Stop'];
        if ($withStatus) {
            $headers[] = 'Status';
        }

        $io->table(
            $headers,
            $rows
        );

        return Command::SUCCESS;
    }
}
