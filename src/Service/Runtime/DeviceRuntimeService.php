<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Runtime;

use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Factory\RuntimeDeviceFactory;
use EvilStudio\HAT\Service\Application\DeviceService;

class DeviceRuntimeService
{
    public function __construct(
        protected DeviceService $deviceService,
        protected RuntimeDeviceFactory $runtimeDeviceFactory
    ) {
    }

    public function listRuntimeDevices(): array
    {
        $runtimeDevices = [];
        foreach ($this->deviceService->listDevices() as $deviceEntity) {
            $runtimeDevice = $this->runtimeDeviceFactory->createFromEntity($deviceEntity);
            $runtimeDevices[$runtimeDevice->getName()] = $runtimeDevice;
        }

        return $runtimeDevices;
    }

    public function getRuntimeDeviceByName(string $deviceName): DeviceInterface
    {
        $deviceEntity = $this->deviceService->getDeviceByName($deviceName);

        return $this->runtimeDeviceFactory->createFromEntity($deviceEntity);
    }

    public function listRuntimeDeviceNames(): array
    {
        return array_keys($this->listRuntimeDevices());
    }
}
