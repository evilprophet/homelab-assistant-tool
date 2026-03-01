<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Factory;

use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Runtime\Device\Generic;
use EvilStudio\HAT\Runtime\Device\Linux;

class RuntimeDeviceFactory
{
    public function __construct(
        protected Configuration $configuration
    ) {
    }

    public function createFromEntity(Device $device): DeviceInterface
    {
        $runtimeDevice = $this->createRuntimeDeviceByPlatform($device->getPlatform());
        $ups = $device->getUps();
        $runtimeDevice->configure(
            $device->getId(),
            $device->getName(),
            $device->getIp(),
            $device->getMac(),
            $device->getPlatform(),
            $ups?->getId(),
            $ups?->getName(),
            $ups?->getIdentifier(),
            $device->getUpsLowBatteryRuntimeThreshold(),
            $device->getUsername()
        );

        return $runtimeDevice;
    }

    protected function createRuntimeDeviceByPlatform(string $platform): DeviceInterface
    {
        $resolvedPlatform = DevicePlatform::tryFrom($platform);
        if ($resolvedPlatform === null) {
            return new Generic($this->configuration);
        }

        if ($resolvedPlatform->usesLinuxRuntime()) {
            return new Linux($this->configuration);
        }

        return match ($resolvedPlatform) {
            default => new Generic($this->configuration)
        };
    }
}
