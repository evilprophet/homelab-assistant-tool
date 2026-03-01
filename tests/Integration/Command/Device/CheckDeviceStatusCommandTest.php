<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Device;

use EvilStudio\HAT\Command\Device\CheckDeviceStatusCommand;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CheckDeviceStatusCommandTest extends TestCase
{
    public function testExecuteShowsDeviceStatusForExistingDevice(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $selectedDevice = $this->createMock(DeviceInterface::class);
        $checkedDevice = $this->createMock(DeviceInterface::class);

        $selectedDevice->expects($this->once())->method('getName')->willReturn('node-1');
        $operations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($selectedDevice);
        $operations->expects($this->once())->method('checkDeviceStatus')->with('node-1')->willReturn($checkedDevice);
        $checkedDevice->expects($this->once())->method('getName')->willReturn('node-1');
        $checkedDevice->expects($this->once())->method('toArray')->willReturn(['status' => 'online']);

        $tester = new CommandTester(new CheckDeviceStatusCommand($operations));
        $exitCode = $tester->execute(['name' => 'node-1'], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Status for device 'node-1': online.", $tester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenDeviceDoesNotExist(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $operations->expects($this->once())
            ->method('getDevice')
            ->with('missing')
            ->willThrowException(new EntityNotFound("Device with name 'missing' not found."));
        $operations->expects($this->never())->method('checkDeviceStatus');

        $tester = new CommandTester(new CheckDeviceStatusCommand($operations));
        $exitCode = $tester->execute(['name' => 'missing'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString("Device with name 'missing' not found.", $tester->getDisplay());
    }
}
