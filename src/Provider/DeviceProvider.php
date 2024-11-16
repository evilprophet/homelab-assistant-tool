<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Provider;

use EvilStudio\HAT\Api\DeviceInterface;
use EvilStudio\HAT\Exception\MissingDevice;
use EvilStudio\HAT\Model\DeviceFactory;

class DeviceProvider
{
    protected array $properties = ['Name', 'Platform', 'IP', 'MAC'];
    protected array $deviceList = [];

    public function __construct(
        protected DeviceFactory $deviceFactory,
        array $devicesData
    )
    {
        foreach ($devicesData as $deviceData) {
            $device = $this->deviceFactory->createDevice($deviceData['platform']);
            $device->configure(
                $deviceData['name'],
                $deviceData['platform'],
                $deviceData['ip'],
                $deviceData['mac'],
                $deviceData['username'] ?? null
            );

            $this->deviceList[$device->getName()] = $device;
        }
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getDevices(): array
    {
        return $this->deviceList;
    }

    public function getDevice(string $deviceName): DeviceInterface
    {
        if (!array_key_exists($deviceName, $this->deviceList)) {
            throw new MissingDevice(sprintf("Device with name '%s' not found.", $deviceName));
        }

        return $this->deviceList[$deviceName];
    }

    public function checkStatus(): void
    {
        if (!array_key_exists('status', $this->properties)) {
            $this->properties[] = 'Status';
        }

        foreach ($this->deviceList as $device) {
            $device->checkStatus();
        }
    }
}
