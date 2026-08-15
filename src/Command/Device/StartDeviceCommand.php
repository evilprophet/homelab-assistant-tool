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
use Throwable;

#[AsCommand(name: 'hat:device:start', description: 'Start device')]
class StartDeviceCommand extends AbstractDeviceCommand
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

        try {
            $result = $this->deviceOperationsService->startDevice($device->getName());
        } catch (Throwable $exception) {
            $outputHelper->error($exception->getMessage());

            return Command::FAILURE;
        }

        // Success only means the magic packet left the host; nothing here confirms
        // the device supports WOL, is cabled, or actually boots.
        $message = $result
            ? sprintf("Wake-on-LAN packet sent to device '%s'.", $device->getName())
            : sprintf("Wake-on-LAN packet could not be sent to device '%s'.", $device->getName());
        $outputHelper->note($message);

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::DEVICE_START->value,
            $result ? ActionLog::LEVEL_INFO : ActionLog::LEVEL_WARNING,
            $message
        );

        // A script chaining on `&&` must not treat a failed action as done.
        return $result ? Command::SUCCESS : Command::FAILURE;
    }
}
