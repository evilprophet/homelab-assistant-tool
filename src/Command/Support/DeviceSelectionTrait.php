<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Support;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait DeviceSelectionTrait
{
    protected function resolveDeviceId(
        InputInterface $input,
        SymfonyStyle $io,
        string $question = 'Device',
        string $emptyListMessage = 'No devices available.',
        bool $requireArgumentWhenNonInteractive = true
    ): int|false {
        $idArgument = $input->getArgument('id');
        if (is_string($idArgument) && trim($idArgument) !== '') {
            $normalized = filter_var($idArgument, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($normalized === false) {
                $io->error('id must be a positive integer.');

                return false;
            }

            return (int)$normalized;
        }

        if ($requireArgumentWhenNonInteractive && !$input->isInteractive()) {
            $io->error("Argument 'id' is required.");

            return false;
        }

        $devices = $this->deviceService->listDevices();
        if (empty($devices)) {
            $io->error($emptyListMessage);

            return false;
        }

        $choices = [];
        foreach ($devices as $device) {
            $deviceId = $device->getId();
            if ($deviceId === null) {
                continue;
            }

            $label = sprintf('%d: %s', $deviceId, $device->getName());
            $choices[$label] = $deviceId;
        }

        if (empty($choices)) {
            $io->error($emptyListMessage);

            return false;
        }

        $selected = $io->choice($question, array_keys($choices));

        return (int)$choices[$selected];
    }
}
