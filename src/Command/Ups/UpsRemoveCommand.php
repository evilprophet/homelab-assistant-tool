<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Ups;

use EvilStudio\HAT\Command\Support\DestructiveConfirmationTrait;
use EvilStudio\HAT\Command\Support\UpsSelectionTrait;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\UpsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'hat:ups:remove', description: 'Remove UPS')]
class UpsRemoveCommand extends Command
{
    use UpsSelectionTrait;
    use DestructiveConfirmationTrait;

    public function __construct(
        protected UpsService $upsService,
        protected ActionLogService $actionLogService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL, 'UPS ID')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $upsId = $this->resolveUpsId($input, $io, 'Select UPS to remove', 'No UPS entries available to remove.');
        if ($upsId === false) {
            return Command::FAILURE;
        }

        try {
            $ups = $this->upsService->getUpsById($upsId);
        } catch (EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $linkedDeviceNames = [];
        foreach ($ups->getDevices()->toArray() as $device) {
            if ($device instanceof Device) {
                $linkedDeviceNames[] = $device->getName();
            }
        }

        if (!empty($linkedDeviceNames)) {
            $io->warning(
                sprintf(
                    "UPS '%s' is linked to devices: %s. Devices will be detached (ups_id = NULL).",
                    $ups->getIdentifier(),
                    implode(', ', $linkedDeviceNames)
                )
            );
        }

        $confirmationExitCode = $this->confirmDestructiveAction(
            $input,
            $io,
            sprintf("Remove UPS '%s'?", $ups->getIdentifier()),
            'UPS removal aborted by user.'
        );
        if ($confirmationExitCode !== null) {
            return $confirmationExitCode;
        }

        try {
            $this->upsService->removeUps($upsId);
        } catch (EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::UPS_REMOVE->value,
            ActionLog::LEVEL_WARNING,
            sprintf(
                "UPS '%s' removed. Detached devices: %d.",
                $ups->getIdentifier(),
                count($linkedDeviceNames)
            )
        );

        $io->success(sprintf("UPS '%s' removed.", $ups->getIdentifier()));

        return Command::SUCCESS;
    }
}
