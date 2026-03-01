<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Ups;

use EvilStudio\HAT\Command\Support\InteractiveInputTrait;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
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

#[AsCommand(name: 'hat:ups:create', description: 'Create UPS')]
class UpsCreateCommand extends Command
{
    use InteractiveInputTrait;

    public function __construct(
        protected UpsService $upsService,
        protected ActionLogService $actionLogService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'UPS name')
            ->addArgument('identifier', InputArgument::OPTIONAL, 'UPS identifier')
            ->addArgument('host', InputArgument::OPTIONAL, 'UPS host')
            ->addOption(
                'safe-battery-runtime-threshold',
                null,
                InputOption::VALUE_REQUIRED,
                'Safe battery runtime threshold in seconds'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $this->resolveRequiredArgument($input, $io, 'name', 'UPS name');
        if ($name === null) {
            return Command::FAILURE;
        }

        $identifier = $this->resolveRequiredArgument($input, $io, 'identifier', 'UPS identifier');
        if ($identifier === null) {
            return Command::FAILURE;
        }

        $host = $this->resolveRequiredArgument($input, $io, 'host', 'UPS host');
        if ($host === null) {
            return Command::FAILURE;
        }

        $safeBatteryRuntimeThreshold = $this->parseOptionalNonNegativeInt(
            $input->getOption('safe-battery-runtime-threshold'),
            '--safe-battery-runtime-threshold',
            $io
        );
        if ($safeBatteryRuntimeThreshold === false) {
            return Command::FAILURE;
        }

        if (
            $safeBatteryRuntimeThreshold === null
            && !$input->hasParameterOption('--safe-battery-runtime-threshold')
            && $input->isInteractive()
        ) {
            $safeBatteryRuntimeThreshold = $this->promptOptionalNonNegativeInt(
                $io,
                'Safe battery runtime threshold in seconds (optional)',
                null
            );
            if ($safeBatteryRuntimeThreshold === false) {
                return Command::FAILURE;
            }
        }

        try {
            $ups = $this->upsService->createUps($name, $identifier, $host, $safeBatteryRuntimeThreshold);
        } catch (EntityAlreadyExists $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("UPS '%s' created with ID %d.", $ups->getName(), (int)$ups->getId()));

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::UPS_CREATE->value,
            ActionLog::LEVEL_INFO,
            sprintf("UPS '%s' created with ID %d.", $ups->getIdentifier(), (int)$ups->getId())
        );

        return Command::SUCCESS;
    }
}
