<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Model;

use EvilStudio\HAT\Api\DeviceInterface;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Device\Generic;
use EvilStudio\HAT\Model\Device\Linux;
use EvilStudio\HAT\Model\Device\Windows;

class DeviceFactory
{
    public function __construct(
        protected Configuration $configuration
    )
    {
    }

    public function createDevice(string $platform): DeviceInterface
    {
        return match ($platform) {
            'debian', 'ubuntu', 'linux', 'proxmox_bs', 'proxmox_ve' => new Linux($this->configuration),
            'windows' => new Windows($this->configuration),
            default => new Generic($this->configuration),
        };
    }
}
