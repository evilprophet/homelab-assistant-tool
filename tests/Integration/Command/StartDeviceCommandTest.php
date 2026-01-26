<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command;

use EvilStudio\HAT\Command\Device\StartDeviceCommand;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Api\DeviceInterface;
use EvilStudio\HAT\Model\DeviceFactory;
use EvilStudio\HAT\Provider\DeviceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class StartDeviceCommandTest extends TestCase
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

    protected function createCommand(array $devicesData = []): StartDeviceCommand
    {
        $deviceProvider = new DeviceProvider($this->configuration, $this->deviceFactory, $devicesData);

        return new StartDeviceCommand($deviceProvider);
    }

    protected function createCommandWithMockedDevice(string $deviceName, bool $startResult = true): StartDeviceCommand
    {
        $deviceMock = $this->createMock(DeviceInterface::class);
        $deviceMock->method('getName')->willReturn($deviceName);
        $deviceMock->method('start')->willReturn($startResult);

        $providerMock = $this->createMock(DeviceProvider::class);
        $providerMock->method('getDevice')->with($deviceName)->willReturn($deviceMock);
        $providerMock->method('getDeviceList')->willReturn([$deviceName => $deviceMock]);

        return new StartDeviceCommand($providerMock);
    }

    public function testExecuteWithDeviceName(): void
    {
        $command = $this->createCommandWithMockedDevice('Test Server');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Test Server']);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Test Server', $output);
        $this->assertStringContainsString('started', $output);
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
        $this->assertStringContainsString('started', $output);
    }

    public function testCommandHasCorrectName(): void
    {
        $command = $this->createCommand([]);

        $this->assertEquals('hat:device:start', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->createCommand([]);

        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('start', strtolower($command->getDescription()));
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
        $command = $this->createCommandWithMockedDevice('Server 2');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Server 2']);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Server 2', $output);
        $this->assertStringNotContainsString('Server 1', $output);
    }

    public function testExecuteWithLinuxDevice(): void
    {
        $command = $this->createCommandWithMockedDevice('Linux Server');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Linux Server']);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Linux Server', $output);
    }
}
