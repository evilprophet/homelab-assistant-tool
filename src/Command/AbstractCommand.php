<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command;

use EvilStudio\HAT\Api\DeviceInterface;
use EvilStudio\HAT\Provider\DeviceProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command
{
    public function __construct(
        protected DeviceProvider $deviceProvider
    )
    {
        parent::__construct();
    }

    protected function getDevice(InputInterface $input, SymfonyStyle $outputHelper): DeviceInterface
    {
        $name = $input->getArgument('name');

        if (!$name) {
            $deviceNames = array_keys($this->deviceProvider->getDevices());
            $name = $outputHelper->choice('Please select a device', $deviceNames);
        }

        return $this->deviceProvider->getDevice($name);
    }
}
