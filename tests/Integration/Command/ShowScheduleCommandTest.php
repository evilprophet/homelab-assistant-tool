<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command;

use EvilStudio\HAT\Command\Schedule\ShowScheduleCommand;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Provider\ScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ShowScheduleCommandTest extends TestCase
{
    protected function createCommand(array $schedulesData = []): ShowScheduleCommand
    {
        $config = [
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'testuser',
            'timezone' => 'UTC',
        ];

        $configuration = new Configuration($config);
        $scheduleProvider = new ScheduleProvider($configuration, $schedulesData);

        return new ShowScheduleCommand($scheduleProvider);
    }

    public function testExecuteWithEmptyScheduleList(): void
    {
        $command = $this->createCommand([]);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Schedule', $output);
        $this->assertStringContainsString('Command', $output);
        $this->assertStringContainsString('Devices', $output);
    }

    public function testExecuteWithSingleSchedule(): void
    {
        $schedulesData = [
            [
                'name' => 'Morning Start',
                'schedule' => '0 8 * * *',
                'command' => 'start',
                'devices' => ['device1', 'device2'],
            ],
        ];

        $command = $this->createCommand($schedulesData);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Morning Start', $output);
        $this->assertStringContainsString('0 8 * * *', $output);
        $this->assertStringContainsString('start', $output);
        $this->assertStringContainsString('device1, device2', $output);
    }

    public function testExecuteWithMultipleSchedules(): void
    {
        $schedulesData = [
            [
                'name' => 'Morning Start',
                'schedule' => '0 8 * * *',
                'command' => 'start',
                'devices' => ['device1'],
            ],
            [
                'name' => 'Evening Stop',
                'schedule' => '0 22 * * *',
                'command' => 'stop',
                'devices' => ['device2'],
            ],
            [
                'name' => 'Midday Check',
                'schedule' => '0 12 * * *',
                'command' => 'start',
                'devices' => ['device3', 'device4'],
            ],
        ];

        $command = $this->createCommand($schedulesData);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Morning Start', $output);
        $this->assertStringContainsString('Evening Stop', $output);
        $this->assertStringContainsString('Midday Check', $output);
        $this->assertStringContainsString('0 8 * * *', $output);
        $this->assertStringContainsString('0 22 * * *', $output);
        $this->assertStringContainsString('0 12 * * *', $output);
    }

    public function testExecuteDisplaysTableFormat(): void
    {
        $schedulesData = [
            [
                'name' => 'Test Schedule',
                'schedule' => '*/5 * * * *',
                'command' => 'start',
                'devices' => ['test-device'],
            ],
        ];

        $command = $this->createCommand($schedulesData);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('-', $output);
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Schedule', $output);
    }

    public function testCommandHasCorrectName(): void
    {
        $command = $this->createCommand([]);

        $this->assertEquals('hat:schedule:show-all', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->createCommand([]);

        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('schedule', strtolower($command->getDescription()));
    }
}
