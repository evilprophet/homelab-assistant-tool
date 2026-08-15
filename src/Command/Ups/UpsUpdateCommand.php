<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Ups;

use EvilStudio\HAT\Command\Support\InteractiveInputTrait;
use EvilStudio\HAT\Command\Support\UpsSelectionTrait;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
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

#[AsCommand(name: 'hat:ups:update', description: 'Update UPS')]
class UpsUpdateCommand extends Command
{
    use InteractiveInputTrait;
    use UpsSelectionTrait;

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
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'UPS name')
            ->addOption('identifier', null, InputOption::VALUE_REQUIRED, 'UPS identifier')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'UPS host')
            ->addOption(
                'safe-battery-runtime-threshold',
                null,
                InputOption::VALUE_REQUIRED,
                'Safe battery runtime threshold in seconds'
            )
            ->addOption(
                'clear-safe-battery-runtime-threshold',
                null,
                InputOption::VALUE_NONE,
                'Clear safe battery runtime threshold'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (
            $input->getOption('safe-battery-runtime-threshold') !== null
            && $input->getOption('clear-safe-battery-runtime-threshold')
        ) {
            $io->error(
                'Use either --safe-battery-runtime-threshold or --clear-safe-battery-runtime-threshold, not both.'
            );

            return Command::FAILURE;
        }

        $upsId = $this->resolveUpsId($input, $io, 'UPS', 'No UPS entries available to update.');
        if ($upsId === false) {
            return Command::FAILURE;
        }

        try {
            $ups = $this->upsService->getUpsById($upsId);
        } catch (EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $safeBatteryRuntimeThresholdOption = $this->parseOptionalNonNegativeInt(
            $input->getOption('safe-battery-runtime-threshold'),
            '--safe-battery-runtime-threshold',
            $io
        );
        if ($safeBatteryRuntimeThresholdOption === false) {
            return Command::FAILURE;
        }

        $name = $this->resolveStringOption($input, $io, 'name', 'UPS name', $ups->getName());
        if ($name === null) {
            return Command::FAILURE;
        }

        $identifier = $this->resolveStringOption($input, $io, 'identifier', 'UPS identifier', $ups->getIdentifier());
        if ($identifier === null) {
            return Command::FAILURE;
        }

        $host = $this->resolveStringOption($input, $io, 'host', 'UPS host', $ups->getHost());
        if ($host === null) {
            return Command::FAILURE;
        }

        $safeBatteryRuntimeThreshold = $ups->getSafeBatteryRuntimeThreshold();
        if ($input->getOption('clear-safe-battery-runtime-threshold')) {
            $safeBatteryRuntimeThreshold = null;
        } elseif ($input->hasParameterOption('--safe-battery-runtime-threshold')) {
            $safeBatteryRuntimeThreshold = $safeBatteryRuntimeThresholdOption;
        } elseif ($input->isInteractive()) {
            $safeBatteryRuntimeThreshold = $this->promptOptionalNonNegativeInt(
                $io,
                sprintf('Safe battery runtime threshold in seconds (enter %s to clear)', self::CLEAR_SENTINEL),
                $safeBatteryRuntimeThreshold
            );
            if ($safeBatteryRuntimeThreshold === false) {
                return Command::FAILURE;
            }
        }

        try {
            $updatedUps = $this->upsService->updateUps(
                $upsId,
                $name,
                $identifier,
                $host,
                $safeBatteryRuntimeThreshold
            );
        } catch (EntityAlreadyExists | EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("UPS '%s' updated.", $updatedUps->getName()));

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::UPS_UPDATE->value,
            ActionLog::LEVEL_INFO,
            sprintf("UPS '%s' updated.", $updatedUps->getIdentifier())
        );

        return Command::SUCCESS;
    }
}
