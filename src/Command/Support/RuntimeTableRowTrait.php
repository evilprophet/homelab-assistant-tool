<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Support;

trait RuntimeTableRowTrait
{
    protected const string EMPTY_CELL = '-';

    /**
     * Runtime view models expose device links as structures. Console tables need a
     * single cell, so the joining happens here instead of in the view model.
     */
    protected function formatDeviceList(array $devices): string
    {
        $names = [];
        foreach ($devices as $device) {
            $name = is_array($device) ? ($device['name'] ?? null) : null;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names === [] ? self::EMPTY_CELL : implode(', ', $names);
    }

    protected function formatEntityLink(?array $entity): string
    {
        $name = $entity['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : self::EMPTY_CELL;
    }
}
