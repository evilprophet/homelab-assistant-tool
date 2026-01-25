<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command;

use EvilStudio\HAT\Command\Device\ShowDevicesCommand;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\DeviceFactory;
use EvilStudio\HAT\Provider\DeviceProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ShowDevicesCommandTest extends TestCase
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

    protected function createCommand(array $devicesData = []): ShowDevicesCommand
    {
        $deviceProvider = new DeviceProvider($this->configuration, $this->deviceFactory, $devicesData);
        return new ShowDevicesCommand($deviceProvider);
    }

    public function testExecuteWithEmptyDeviceList(): void
    {
        $command = $this->createCommand([]);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('IP', $output);
        $this->assertStringContainsString('MAC', $output);
        $this->assertStringContainsString('Platform', $output);
    }

    public function testExecuteWithSingleDevice(): void
    {
        $devicesData = [
            [
                'name' => 'Server 1',
                'ip' => '192.168.1.10',
                'mac' => '00:11:22:33:44:55',
                'platform' => 'linux',
            ],
        ];

        $command = $this->createCommand($devicesData);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Server 1', $output);
        $this->assertStringContainsString('192.168.1.10', $output);
        $this->assertStringContainsString('00:11:22:33:44:55', $output);
        $this->assertStringContainsString('linux', $output);
    }

    public function testExecuteWithMultipleDevices(): void
    {
        $devicesData = [
            [
                'name' => 'Server 1',
                'ip' => '192.168.1.10',
                'mac' => '00:11:22:33:44:55',
                'platform' => 'linux',
            ],
            [
                'name' => 'Server 2',
                'ip' => '192.168.1.20',
                'mac' => 'AA:BB:CC:DD:EE:FF',
                'platform' => 'generic',
            ],
            [
                'name' => 'Proxmox',
                'ip' => '192.168.1.30',
                'mac' => 'FF:EE:DD:CC:BB:AA',
                'platform' => 'proxmox_ve',
            ],
        ];

        $command = $this->createCommand($devicesData);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Server 1', $output);
        $this->assertStringContainsString('Server 2', $output);
        $this->assertStringContainsString('Proxmox', $output);
    }

    public function testExecuteWithUpsInformation(): void
    {
        $devicesData = [
            [
                'name' => 'Server with UPS',
                'ip' => '192.168.1.10',
                'mac' => '00:11:22:33:44:55',
                'platform' => 'linux',
                'ups_identifier' => 'ups1',
                'ups_low_battery_runtime_threshold' => 600,
            ],
        ];

        $command = $this->createCommand($devicesData);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('ups1', $output);
        $this->assertStringContainsString('10 min', $output);
    }

    public function testExecuteDisplaysTableFormat(): void
    {
        $devicesData = [
            [
                'name' => 'Test Device',
                'ip' => '192.168.1.1',
                'mac' => '00:00:00:00:00:01',
                'platform' => 'generic',
            ],
        ];

        $command = $this->createCommand($devicesData);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('-', $output);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('IP', $output);
    }

    public function testCommandHasCorrectName(): void
    {
        $command = $this->createCommand([]);

        $this->assertEquals('hat:device:show-all', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->createCommand([]);

        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('device', strtolower($command->getDescription()));
    }

    public function testCommandHasWithStatusOption(): void
    {
        $command = $this->createCommand([]);

        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('with-status'));

        $option = $definition->getOption('with-status');
        $this->assertFalse($option->isValueRequired());
    }

    public function testExecuteWithoutStatusOption(): void
    {
        $devicesData = [
            [
                'name' => 'Test Device',
                'ip' => '192.168.1.1',
                'mac' => '00:00:00:00:00:01',
                'platform' => 'generic',
            ],
        ];

        $command = $this->createCommand($devicesData);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringNotContainsString('Status', $output);
        $this->assertStringNotContainsString('online', $output);
        $this->assertStringNotContainsString('offline', $output);
    }
}
