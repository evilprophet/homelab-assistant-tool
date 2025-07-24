<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Model;

use EvilStudio\HAT\Api\DeviceInterface;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Device\Generic;
use EvilStudio\HAT\Model\Device\Linux;
use EvilStudio\HAT\Model\Device\ProxmoxVE;

class DeviceFactory
{
    public function __construct(
        protected Configuration $configuration
    ) {
    }

    public function createDevice(string $platform): DeviceInterface
    {
        return match ($platform) {
            'linux', 'debian', 'ubuntu', 'proxmox_dm', 'proxmox_bs' => new Linux($this->configuration),
            'proxmox_ve' => new ProxmoxVE($this->configuration),
            default => new Generic($this->configuration),
        };
    }
}
