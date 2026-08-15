<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Device;

use EvilStudio\HAT\Command\Device\SshIntoDeviceCommand;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SshIntoDeviceCommandTest extends TestCase
{
    public function testExecuteOpensSshSessionForDevice(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $device = $this->createMock(DeviceInterface::class);

        $operations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($device);
        $device->expects($this->once())->method('getName')->willReturn('node-1');
        $operations->expects($this->once())
            ->method('sshIntoDevice')
            ->with('node-1', $this->anything())
            ->willReturn(0);

        $tester = new CommandTester(new SshIntoDeviceCommand($operations));
        $exitCode = $tester->execute(['name' => 'node-1'], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteReturnsTheSshExitCode(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $device = $this->createMock(DeviceInterface::class);

        $operations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($device);
        $device->expects($this->once())->method('getName')->willReturn('node-1');
        $operations->expects($this->once())
            ->method('sshIntoDevice')
            ->with('node-1', $this->anything())
            ->willReturn(255);

        $tester = new CommandTester(new SshIntoDeviceCommand($operations));
        $exitCode = $tester->execute(['name' => 'node-1'], ['interactive' => false]);

        $this->assertSame(255, $exitCode);
    }

    public function testExecuteReturnsFailureWhenSshFails(): void
    {
        $operations = $this->createMock(DeviceOperationsService::class);
        $device = $this->createMock(DeviceInterface::class);

        $operations->expects($this->once())->method('getDevice')->with('node-1')->willReturn($device);
        $device->expects($this->once())->method('getName')->willReturn('node-1');
        $operations->expects($this->once())
            ->method('sshIntoDevice')
            ->with('node-1', $this->anything())
            ->willThrowException(new RuntimeException('SSH connection failed.'));

        $tester = new CommandTester(new SshIntoDeviceCommand($operations));
        $exitCode = $tester->execute(['name' => 'node-1'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('SSH connection failed.', $tester->getDisplay());
    }
}
