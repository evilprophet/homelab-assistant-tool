<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command;

use EvilStudio\HAT\Api\DeviceInterface;
use EvilStudio\HAT\Command\Device\StopDeviceCommand;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\DeviceFactory;
use EvilStudio\HAT\Provider\DeviceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class StopDeviceCommandTest extends TestCase
{
    protected Configuration $configuration;
    protected DeviceFactory $deviceFactory;

    protected function setUp(): void
    {
        $config = [
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'testuser',
            'timezone' => 'UTC',
        ];

        $this->configuration = new Configuration($config);
        $this->deviceFactory = new DeviceFactory($this->configuration);
    }

    protected function createCommand(array $devicesData = []): StopDeviceCommand
    {
        $deviceProvider = new DeviceProvider($this->configuration, $this->deviceFactory, $devicesData);
        return new StopDeviceCommand($deviceProvider);
    }

    protected function createCommandWithMockedDevice(string $deviceName, bool $stopResult = true): StopDeviceCommand
    {
        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getName')->willReturn($deviceName);
        $deviceMock->method('stop')->willReturn($stopResult);

        $providerMock = $this->createMock(DeviceProvider::class);
        $providerMock->method('getDevice')->willReturn($deviceMock);
        $providerMock->method('getDeviceList')->willReturn([$deviceName => $deviceMock]);

        return new StopDeviceCommand($providerMock);
    }

    public function testExecuteWithLinuxDevice(): void
    {
        $command = $this->createCommandWithMockedDevice('Linux Server');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Linux Server']);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Linux Server', $output);
        $this->assertStringContainsString('stopped', $output);
    }

    public function testExecuteWithNonExistentDevice(): void
    {
        $devicesData = [
            [
                'name' => 'Test Server',
                'ip' => '192.168.1.10',
                'mac' => '00:11:22:33:44:55',
                'platform' => 'generic',
            ],
        ];

        $command = $this->createCommand($devicesData);
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'NonExistent']);

        $this->assertEquals(Command::FAILURE, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('not found', $output);
    }

    public function testExecuteDisplaysSuccessMessage(): void
    {
        $command = $this->createCommandWithMockedDevice('Production Server');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Production Server']);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Production Server', $output);
        $this->assertStringContainsString('stopped', $output);
    }

    public function testCommandHasCorrectName(): void
    {
        $command = $this->createCommand([]);

        $this->assertEquals('hat:device:stop', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->createCommand([]);

        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('stop', strtolower($command->getDescription()));
    }

    public function testCommandHasNameArgument(): void
    {
        $command = $this->createCommand([]);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('name'));

        $argument = $definition->getArgument('name');
        $this->assertFalse($argument->isRequired());
    }

    public function testExecuteWithMultipleDevices(): void
    {
        $deviceMock1 = $this->createMock(DeviceInterface::class);
        $deviceMock1->method('getName')->willReturn('Server 1');

        $deviceMock2 = $this->createMock(DeviceInterface::class);
        $deviceMock2->method('getName')->willReturn('Server 2');
        $deviceMock2->method('stop')->willReturn(true);

        $providerMock = $this->createMock(DeviceProvider::class);
        $providerMock->method('getDevice')->with('Server 2')->willReturn($deviceMock2);
        $providerMock->method('getDeviceList')->willReturn([
            'Server 1' => $deviceMock1,
            'Server 2' => $deviceMock2,
        ]);

        $command = new StopDeviceCommand($providerMock);
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Server 2']);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Server 2', $output);
        $this->assertStringNotContainsString('Server 1', $output);
    }

    public function testExecuteWithProxmoxDevice(): void
    {
        $command = $this->createCommandWithMockedDevice('Proxmox Server');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Proxmox Server']);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Proxmox Server', $output);
    }
}
