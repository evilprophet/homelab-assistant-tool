<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Support;

use Symfony\Component\Console\Style\SymfonyStyle;

trait ScheduleDeviceSelectionTrait
{
    protected function parseDeviceIds(array $values, SymfonyStyle $io): ?array
    {
        $deviceIds = [];
        foreach ($values as $value) {
            $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($normalized === false) {
                $io->error('All --device-id values must be positive integers.');

                return null;
            }

            $deviceIds[] = (int)$normalized;
        }

        return array_values(array_unique($deviceIds));
    }

    protected function promptDeviceIds(
        SymfonyStyle $io,
        array $defaultDeviceIds,
        string $question = 'Schedule devices'
    ): ?array {
        $devices = $this->deviceService->listDevices();
        if (empty($devices)) {
            return [];
        }

        $choices = [];
        $mapping = [];
        $defaultChoices = [];

        foreach ($devices as $device) {
            $deviceId = $device->getId();
            if ($deviceId === null) {
                continue;
            }

            $label = sprintf('%d: %s', $deviceId, $device->getName());
            $choices[] = $label;
            $mapping[$label] = $deviceId;

            if (in_array($deviceId, $defaultDeviceIds, true)) {
                $defaultChoices[] = $label;
            }
        }

        if (empty($choices)) {
            return [];
        }

        $defaultSelection = empty($defaultChoices) ? null : implode(',', $defaultChoices);
        $selected = $io->choice($question, $choices, $defaultSelection, true);
        $selectedLabels = is_array($selected) ? $selected : [$selected];

        $selectedIds = [];
        foreach ($selectedLabels as $label) {
            $selectedId = $mapping[$label] ?? null;
            if ($selectedId !== null) {
                $selectedIds[] = (int)$selectedId;
            }
        }

        return array_values(array_unique($selectedIds));
    }
}
