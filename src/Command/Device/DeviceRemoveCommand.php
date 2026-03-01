<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Device;

use EvilStudio\HAT\Command\Support\DeviceSelectionTrait;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Entity\Schedule;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'hat:device:remove', description: 'Remove device')]
class DeviceRemoveCommand extends Command
{
    use DeviceSelectionTrait;

    public function __construct(
        protected DeviceService $deviceService,
        protected ActionLogService $actionLogService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'Device ID')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deviceId = $this->resolveDeviceId(
            $input,
            $io,
            'Select device to remove',
            'No devices available to remove.'
        );
        if ($deviceId === false) {
            return Command::FAILURE;
        }

        try {
            $device = $this->deviceService->getDeviceById($deviceId);
        } catch (EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $linkedScheduleNames = [];
        foreach ($device->getSchedules()->toArray() as $schedule) {
            if ($schedule instanceof Schedule) {
                $linkedScheduleNames[] = $schedule->getName();
            }
        }

        if (!empty($linkedScheduleNames)) {
            $io->warning(
                sprintf(
                    "Device '%s' is linked to schedules: %s. Links will be removed.",
                    $device->getName(),
                    implode(', ', $linkedScheduleNames)
                )
            );
        }

        if (!$input->getOption('force')) {
            if (!$io->confirm(sprintf("Remove device '%s'?", $device->getName()), false)) {
                $io->warning('Device removal aborted by user.');

                return Command::SUCCESS;
            }
        }

        try {
            $this->deviceService->removeDevice($deviceId);
        } catch (EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::DEVICE_REMOVE->value,
            ActionLog::LEVEL_WARNING,
            sprintf(
                "Device '%s' removed. Removed schedule links: %d.",
                $device->getName(),
                count($linkedScheduleNames)
            )
        );

        $io->success(sprintf("Device '%s' removed.", $device->getName()));

        return Command::SUCCESS;
    }
}
