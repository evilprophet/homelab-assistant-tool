<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Device;

use EvilStudio\HAT\Command\Device\AbstractDeviceCommand;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AbstractDeviceCommandTest extends TestCase
{
    public function testGetDeviceUsesProvidedNameArgument(): void
    {
        $operationsService = $this->createMock(DeviceOperationsService::class);
        $command = $this->createCommand($operationsService);
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        $device = $this->createMock(DeviceInterface::class);

        $input->expects($this->once())->method('getArgument')->with('name')->willReturn('node-1');
        $io->expects($this->never())->method('choice');
        $operationsService->expects($this->once())->method('getDevice')->with('node-1')->willReturn($device);

        $result = $command->callGetDevice($input, $io);

        $this->assertSame($device, $result);
    }

    public function testGetDeviceResolvesNameFromChoiceWhenArgumentIsMissing(): void
    {
        $operationsService = $this->createMock(DeviceOperationsService::class);
        $command = $this->createCommand($operationsService);
        $input = $this->createMock(InputInterface::class);
        $io = $this->createMock(SymfonyStyle::class);
        $device = $this->createMock(DeviceInterface::class);

        $input->expects($this->once())->method('getArgument')->with('name')->willReturn(null);
        $input->expects($this->once())->method('isInteractive')->willReturn(true);
        $operationsService->expects($this->once())->method('listDeviceNames')->willReturn(['node-1', 'node-2']);
        $io->expects($this->once())
            ->method('choice')
            ->with('Please select a device', ['node-1', 'node-2'])
            ->willReturn('node-2');
        $operationsService->expects($this->once())->method('getDevice')->with('node-2')->willReturn($device);

        $result = $command->callGetDevice($input, $io);

        $this->assertSame($device, $result);
    }

    protected function createCommand(DeviceOperationsService $operationsService): object
    {
        return new class ($operationsService) extends AbstractDeviceCommand {
            public function callGetDevice(InputInterface $input, SymfonyStyle $outputHelper): DeviceInterface
            {
                return $this->getDevice($input, $outputHelper);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        };
    }
}
