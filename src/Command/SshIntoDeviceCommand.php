<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command;

use EvilStudio\HAT\Exception\MissingDevice;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:device:ssh', description: 'SSH into a device')]
class SshIntoDeviceCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'The name of the device');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputHelper = new SymfonyStyle($input, $output);

        try {
            $device = $this->getDevice($input, $outputHelper);
        } catch (MissingDevice $e) {
            $outputHelper->error($e->getMessage());

            return Command::FAILURE;
        }

        try {
            $device->ssh($output);
        } catch (Exception $e) {
            $outputHelper->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
