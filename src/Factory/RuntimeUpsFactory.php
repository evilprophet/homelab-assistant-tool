<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Factory;

use EvilStudio\HAT\Contract\UpsInterface;
use EvilStudio\HAT\Entity\Ups;
use EvilStudio\HAT\Runtime\Ups as RuntimeUps;

class RuntimeUpsFactory
{
    public function createFromEntity(Ups $ups): UpsInterface
    {
        $linkedDevices = [];
        foreach ($ups->getDevices()->toArray() as $device) {
            $deviceId = $device->getId();
            if ($deviceId === null) {
                continue;
            }

            $linkedDevices[] = ['id' => (int)$deviceId, 'name' => $device->getName()];
        }

        return new RuntimeUps(
            $ups->getId(),
            $ups->getName(),
            $ups->getIdentifier(),
            $ups->getHost(),
            $ups->getSafeBatteryRuntimeThreshold(),
            $linkedDevices
        );
    }
}
