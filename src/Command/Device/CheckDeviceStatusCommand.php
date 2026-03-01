<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Device;

use EvilStudio\HAT\Exception\EntityNotFound;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:device:check-status', description: 'Check device status')]
class CheckDeviceStatusCommand extends AbstractDeviceCommand
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Device name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputHelper = new SymfonyStyle($input, $output);

        try {
            $device = $this->getDevice($input, $outputHelper);
        } catch (EntityNotFound $e) {
            $outputHelper->error($e->getMessage());

            return Command::FAILURE;
        }

        $device = $this->deviceOperationsService->checkDeviceStatus($device->getName());
        $deviceAsArray = $device->toArray();

        $message = sprintf("Status for device '%s': %s.", $device->getName(), $deviceAsArray['status']);
        $outputHelper->note($message);

        return Command::SUCCESS;
    }
}
