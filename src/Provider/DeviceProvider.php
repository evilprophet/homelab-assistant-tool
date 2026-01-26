<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Provider;

use EvilStudio\HAT\Api\DeviceInterface;
use EvilStudio\HAT\Exception\MissingDevice;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\DeviceFactory;

class DeviceProvider extends AbstractProvider
{
    protected array $properties = ['Name', 'IP', 'MAC', 'Platform', 'UPS', 'UPS Low Battery Runtime Threshold'];
    protected array $deviceList = [];

    public function __construct(
        Configuration $configuration,
        protected DeviceFactory $deviceFactory,
        array $devicesData
    ) {
        parent::__construct($configuration);

        foreach ($devicesData as $deviceData) {
            $device = $this->deviceFactory->createDevice($deviceData['platform']);
            $device->configure(
                $deviceData['name'],
                $deviceData['ip'],
                $deviceData['mac'],
                $deviceData['platform'],
                $deviceData['ups_identifier'] ?? null,
                $deviceData['ups_low_battery_runtime_threshold'] ?? null,
                $deviceData['username'] ?? null
            );

            $this->deviceList[$device->getName()] = $device;
        }
    }

    public function getDeviceList(): array
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

    public function checkAllDevicesStatus(): void
    {
        if (!in_array('Status', $this->properties, true)) {
            $this->properties[] = 'Status';
        }

        foreach ($this->deviceList as $device) {
            $device->checkStatus();
        }
    }
}
