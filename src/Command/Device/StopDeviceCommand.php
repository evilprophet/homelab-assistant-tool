<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Device;

use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:device:stop', description: 'Stop device')]
class StopDeviceCommand extends AbstractDeviceCommand
{
    public function __construct(
        DeviceOperationsService $deviceOperationsService,
        protected ActionLogService $actionLogService
    ) {
        parent::__construct($deviceOperationsService);
    }

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

        $result = $this->deviceOperationsService->stopDevice($device->getName());
        $message = sprintf("Device '%s' stopped: %s.", $device->getName(), $result ? 'yes' : 'no');
        $outputHelper->note($message);

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::DEVICE_STOP->value,
            ActionLog::LEVEL_INFO,
            $message
        );

        return Command::SUCCESS;
    }
}
