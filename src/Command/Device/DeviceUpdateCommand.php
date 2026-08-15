<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Device;

use EvilStudio\HAT\Command\Support\DeviceSelectionTrait;
use EvilStudio\HAT\Command\Support\InteractiveInputTrait;
use EvilStudio\HAT\Command\Support\DevicePlatformInputTrait;
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

#[AsCommand(name: 'hat:device:update', description: 'Update device')]
class DeviceUpdateCommand extends Command
{
    use DeviceSelectionTrait;
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
            ->addArgument('id', InputArgument::OPTIONAL, 'Device ID')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Device name')
            ->addOption('ip', null, InputOption::VALUE_REQUIRED, 'Device IP')
            ->addOption('mac', null, InputOption::VALUE_REQUIRED, 'Device MAC')
            ->addOption('platform', null, InputOption::VALUE_REQUIRED, 'Device platform')
            ->addOption('username', null, InputOption::VALUE_REQUIRED, 'SSH username')
            ->addOption('clear-username', null, InputOption::VALUE_NONE, 'Clear SSH username')
            ->addOption('ups-id', null, InputOption::VALUE_REQUIRED, 'UPS ID')
            ->addOption('clear-ups', null, InputOption::VALUE_NONE, 'Detach UPS')
            ->addOption(
                'ups-low-battery-runtime-threshold',
                null,
                InputOption::VALUE_REQUIRED,
                'UPS low battery runtime threshold in seconds'
            )
            ->addOption(
                'clear-ups-low-battery-runtime-threshold',
                null,
                InputOption::VALUE_NONE,
                'Clear UPS low battery runtime threshold'
            )
            ->addOption('allow-auto-stop', null, InputOption::VALUE_NONE, 'Allow automatic shutdown by cron')
            ->addOption('disallow-auto-stop', null, InputOption::VALUE_NONE, 'Disable automatic shutdown by cron');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('username') !== null && $input->getOption('clear-username')) {
            $io->error('Use either --username or --clear-username, not both.');

            return Command::FAILURE;
        }

        if ($input->getOption('ups-id') !== null && $input->getOption('clear-ups')) {
            $io->error('Use either --ups-id or --clear-ups, not both.');

            return Command::FAILURE;
        }

        if (
            $input->getOption('ups-low-battery-runtime-threshold') !== null
            && $input->getOption('clear-ups-low-battery-runtime-threshold')
        ) {
            $io->error(
                'Use either --ups-low-battery-runtime-threshold or --clear-ups-low-battery-runtime-threshold, not both.'
            );

            return Command::FAILURE;
        }

        if ($input->getOption('allow-auto-stop') && $input->getOption('disallow-auto-stop')) {
            $io->error('Use either --allow-auto-stop or --disallow-auto-stop, not both.');

            return Command::FAILURE;
        }

        $deviceId = $this->resolveDeviceId($input, $io, 'Device', 'No devices available to update.');
        if ($deviceId === false) {
            return Command::FAILURE;
        }

        try {
            $device = $this->deviceService->getDeviceById($deviceId);
        } catch (EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $upsIdOption = $this->parseOptionalPositiveInt($input->getOption('ups-id'), '--ups-id', $io);
        if ($upsIdOption === false) {
            return Command::FAILURE;
        }

        $thresholdOption = $this->parseOptionalNonNegativeInt(
            $input->getOption('ups-low-battery-runtime-threshold'),
            '--ups-low-battery-runtime-threshold',
            $io
        );
        if ($thresholdOption === false) {
            return Command::FAILURE;
        }

        $name = $this->resolveStringOption($input, $io, 'name', 'Device name', $device->getName());
        if ($name === null) {
            return Command::FAILURE;
        }

        $ip = $this->resolveStringOption($input, $io, 'ip', 'Device IP', $device->getIp());
        if ($ip === null) {
            return Command::FAILURE;
        }

        $mac = $this->resolveStringOption($input, $io, 'mac', 'Device MAC', $device->getMac());
        if ($mac === null) {
            return Command::FAILURE;
        }

        $platform = $this->resolvePlatform($input, $io, $device->getPlatform());
        if ($platform === null) {
            return Command::FAILURE;
        }

        $username = $this->nullIfEmpty($device->getUsername());
        if ($input->getOption('clear-username')) {
            $username = null;
        } elseif ($input->hasParameterOption('--username')) {
            $username = $this->nullIfEmpty($input->getOption('username'));
        } elseif ($input->isInteractive()) {
            $username = $this->promptOptionalString(
                $io,
                sprintf('SSH username (enter %s to clear)', self::CLEAR_SENTINEL),
                $device->getUsername()
            );
        }

        $upsId = $device->getUps()?->getId();
        if ($input->getOption('clear-ups')) {
            $upsId = null;
        } elseif ($input->hasParameterOption('--ups-id')) {
            $upsId = $upsIdOption;
        } elseif ($input->isInteractive()) {
            $upsId = $this->promptUpsId($io, $upsId);
            if ($upsId === false) {
                return Command::FAILURE;
            }
        }

        $upsLowBatteryRuntimeThreshold = $device->getUpsLowBatteryRuntimeThreshold();
        if ($input->getOption('clear-ups-low-battery-runtime-threshold')) {
            $upsLowBatteryRuntimeThreshold = null;
        } elseif ($input->hasParameterOption('--ups-low-battery-runtime-threshold')) {
            $upsLowBatteryRuntimeThreshold = $thresholdOption;
        } elseif ($input->isInteractive()) {
            $upsLowBatteryRuntimeThreshold = $this->promptOptionalNonNegativeInt(
                $io,
                sprintf('UPS low battery runtime threshold in seconds (enter %s to clear)', self::CLEAR_SENTINEL),
                $upsLowBatteryRuntimeThreshold
            );
            if ($upsLowBatteryRuntimeThreshold === false) {
                return Command::FAILURE;
            }
        }

        $autoStopAllowed = null;
        if ($input->getOption('allow-auto-stop')) {
            $autoStopAllowed = true;
        } elseif ($input->getOption('disallow-auto-stop')) {
            $autoStopAllowed = false;
        } elseif ($input->isInteractive()) {
            $autoStopAllowed = $io->confirm('Auto stop allowed?', $device->isAutoStopAllowed());
        }

        try {
            $updatedDevice = $this->deviceService->updateDevice(
                $deviceId,
                $name,
                $ip,
                $mac,
                $platform,
                $username,
                $upsLowBatteryRuntimeThreshold,
                $upsId,
                $autoStopAllowed
            );
        } catch (EntityAlreadyExists | EntityNotFound $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf("Device '%s' updated.", $updatedDevice->getName()));

        $this->actionLogService->createActionLog(
            ActionLog::SOURCE_CLI,
            ActionLogAction::DEVICE_UPDATE->value,
            ActionLog::LEVEL_INFO,
            sprintf("Device '%s' updated.", $updatedDevice->getName())
        );

        return Command::SUCCESS;
    }

    protected function resolvePlatform(InputInterface $input, SymfonyStyle $io, string $defaultPlatform): ?string
    {
        $default = in_array($defaultPlatform, DevicePlatform::values(), true)
            ? $defaultPlatform
            : DevicePlatform::GENERIC->value;

        if ($input->hasParameterOption('--platform')) {
            $platform = $this->normalizePlatform((string)$input->getOption('platform'));
        } elseif ($input->isInteractive()) {
            $platform = (string)$io->choice('Device platform', DevicePlatform::values(), $default);
            $platform = $this->normalizePlatform($platform);
        } else {
            $platform = $default;
        }

        if (!$this->isPlatformSupported($platform, $io)) {
            return null;
        }

        return $platform;
    }
}
