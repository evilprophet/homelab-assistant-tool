<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Device;

use EvilStudio\HAT\Command\Support\DevicePlatformInputTrait;
use EvilStudio\HAT\Command\Support\InteractiveInputTrait;
use EvilStudio\HAT\Command\Support\UpsSelectionTrait;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\UpsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'hat:device:create', description: 'Create device')]
class DeviceCreateCommand extends Command
{
    use InteractiveInputTrait;
    use DevicePlatformInputTrait;
    use UpsSelectionTrait;

    public function __construct(
        protected DeviceService $deviceService,
        protected UpsService $upsService,
        protected ActionLogService $actionLogService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Device name')
            ->addArgument('ip', InputArgument::OPTIONAL, 'Device IP')
            ->addArgument('mac', InputArgument::OPTIONAL, 'Device MAC')
            ->addArgument('platform', InputArgument::OPTIONAL, 'Device platform')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'SSH username')
            ->addOption('ups-id', null, InputOption::VALUE_REQUIRED, 'UPS ID')
            ->addOption(
                'ups-low-battery-runtime-threshold',
                null,
                InputOption::VALUE_REQUIRED,
                'UPS low battery runtime threshold in seconds'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $this->resolveRequiredArgument($input, $io, 'name', 'Device name');
        if ($name === null) {
            return Command::FAILURE;
        }

        $ip = $this->resolveRequiredArgument($input, $io, 'ip', 'Device IP');
        if ($ip === null) {
            return Command::FAILURE;
        }

        $mac = $this->resolveRequiredArgument($input, $io, 'mac', 'Device MAC');
        if ($mac === null) {
            return Command::FAILURE;
        }

        $platform = $this->resolvePlatform($input, $io);
        if ($platform === null) {
            return Command::FAILURE;
        }

        $username = $this->nullIfEmpty($input->getOption('username'));
        if ($username === null && !$input->hasParameterOption('--username') && $input->isInteractive()) {
            $username = $this->nullIfEmpty($io->ask('SSH username (optional)', ''));
        }

        $upsId = $this->parseOptionalPositiveInt($input->getOption('ups-id'), '--ups-id', $io);
        if ($upsId === false) {
            return Command::FAILURE;
        }

        if ($upsId === null && !$input->hasParameterOption('--ups-id') && $input->isInteractive()) {
            $upsId = $this->promptUpsId($io);
            if ($upsId === false) {
                return Command::FAILURE;
            }
        }

        $upsLowBatteryRuntimeThreshold = $this->parseOptionalNonNegativeInt(
            $input->getOption('ups-low-battery-runtime-threshold'),
            '--ups-low-battery-runtime-threshold',
            $io
        );
        if ($upsLowBatteryRuntimeThreshold === false) {
            return Command::FAILURE;
        }

        if (
            $upsLowBatteryRuntimeThreshold === null
            && !$input->hasParameterOption('--ups-low-battery-runtime-threshold')
            && $input->isInteractive()
        ) {
            $upsLowBatteryRuntimeThreshold = $this->promptOptionalNonNegativeInt(
                $io,
                'UPS low battery runtime threshold in seconds (optional)',
                null
            );
            if ($upsLowBatteryRuntimeThreshold === false) {
                return Command::FAILURE;
            }
        }

        try {
            $device = $this->deviceService->createDevice(
                $name,
                $ip,
                $mac,
                $platform,
                $username,
                $upsLowBatteryRuntimeThreshold,
                $upsId
            );
        } catch (EntityAlreadyExists | EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Device '%s' created with ID %d.", $device->getName(), (int)$device->getId()));

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::DEVICE_CREATE->value,
            ActionLog::LEVEL_INFO,
            sprintf("Device '%s' created with ID %d.", $device->getName(), (int)$device->getId())
        );

        return Command::SUCCESS;
    }

    protected function resolvePlatform(InputInterface $input, SymfonyStyle $io): ?string
    {
        $platformArgument = $input->getArgument('platform');
        if (is_string($platformArgument) && trim($platformArgument) !== '') {
            $platform = $this->normalizePlatform($platformArgument);
        } elseif ($input->isInteractive()) {
            $platform = (string)$io->choice(
                'Device platform',
                DevicePlatform::values(),
                DevicePlatform::GENERIC->value
            );
            $platform = $this->normalizePlatform($platform);
        } else {
            $io->error("Argument 'platform' is required.");

            return null;
        }

        if (!$this->isPlatformSupported($platform, $io)) {
            return null;
        }

        return $platform;
    }
}
