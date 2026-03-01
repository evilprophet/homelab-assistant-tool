<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Device;

use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractDeviceCommand extends Command
{
    public function __construct(
        protected DeviceOperationsService $deviceOperationsService
    ) {
        parent::__construct();
    }

    protected function getDevice(InputInterface $input, SymfonyStyle $outputHelper): DeviceInterface
    {
        $name = $input->getArgument('name');

        if (!$name) {
            $deviceNames = $this->deviceOperationsService->listDeviceNames();
            $name = $outputHelper->choice('Please select a device', $deviceNames);
        }

        return $this->deviceOperationsService->getDevice((string)$name);
    }
}
