<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Runtime;

use EvilStudio\HAT\Contract\DeviceInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeviceOperationsService
{
    public function __construct(
        protected DeviceRuntimeService $deviceRuntimeService
    ) {
    }

    public function listDevices(bool $withStatus = false): array
    {
        $devices = $this->deviceRuntimeService->listRuntimeDevices();

        if ($withStatus) {
            foreach ($devices as $device) {
                $device->checkStatus();
            }
        }

        return $devices;
    }

    public function listDeviceNames(): array
    {
        return $this->deviceRuntimeService->listRuntimeDeviceNames();
    }

    public function getDevice(string $name): DeviceInterface
    {
        return $this->deviceRuntimeService->getRuntimeDeviceByName($name);
    }

    public function checkDeviceStatus(string $name): DeviceInterface
    {
        $device = $this->getDevice($name);
        $device->checkStatus();

        return $device;
    }

    public function startDevice(string $name): bool
    {
        return $this->getDevice($name)->start();
    }

    public function stopDevice(string $name): bool
    {
        return $this->getDevice($name)->stop();
    }

    public function sshIntoDevice(string $name, OutputInterface $output): void
    {
        $this->getDevice($name)->ssh($output);
    }
}
