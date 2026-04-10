<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Runtime;

use EvilStudio\HAT\Contract\DeviceAction;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Exception\UnsupportedDeviceAction;
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
        $device = $this->getDevice($name);
        $this->assertDeviceActionSupported($device, DeviceAction::START);

        return $device->start();
    }

    public function stopDevice(string $name): bool
    {
        $device = $this->getDevice($name);
        $this->assertDeviceActionSupported($device, DeviceAction::STOP);

        return $device->stop();
    }

    public function sshIntoDevice(string $name, OutputInterface $output): void
    {
        $device = $this->getDevice($name);
        $this->assertDeviceActionSupported($device, DeviceAction::SSH);

        $device->ssh($output);
    }

    public function assertDeviceActionSupported(DeviceInterface $device, DeviceAction $action): void
    {
        $platform = $device->getPlatform();
        if ($this->supportsPlatformAction($platform, $action)) {
            return;
        }

        throw UnsupportedDeviceAction::forPlatform($action, $platform);
    }

    public function supportsDeviceAction(DeviceInterface $device, DeviceAction $action): bool
    {
        return $this->supportsPlatformAction($device->getPlatform(), $action);
    }

    protected function supportsPlatformAction(string $platform, DeviceAction $action): bool
    {
        $resolvedPlatform = DevicePlatform::tryFrom($platform);
        if ($resolvedPlatform === null) {
            return $action === DeviceAction::START;
        }

        return $resolvedPlatform->supportsAction($action);
    }
}
