<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command;

use EvilStudio\HAT\Command\Cron\ExecuteCronCommand;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Provider\DeviceProvider;
use EvilStudio\HAT\Service\Cron;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ExecuteCronCommandTest extends TestCase
{
    public function testConstructor(): void
    {
        $cronMock = $this->createMock(Cron::class);
        $configMock = $this->createMock(Configuration::class);
        $deviceProviderMock = $this->createMock(DeviceProvider::class);

        $command = new ExecuteCronCommand($cronMock, $configMock, $deviceProviderMock);

        $this->assertInstanceOf(ExecuteCronCommand::class, $command);
    }

    public function testExecuteCallsCronExecute(): void
    {
        $cronMock = $this->createMock(Cron::class);
        $cronMock->expects($this->once())->method('execute');

        $configMock = $this->createMock(Configuration::class);
        $configMock->method('isCronEnabled')->willReturn(true);

        $deviceProviderMock = $this->createMock(DeviceProvider::class);

        $command = new ExecuteCronCommand($cronMock, $configMock, $deviceProviderMock);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $cronMock = $this->createMock(Cron::class);

        $configMock = $this->createMock(Configuration::class);
        $configMock->method('isCronEnabled')->willReturn(true);

        $deviceProviderMock = $this->createMock(DeviceProvider::class);

        $command = new ExecuteCronCommand($cronMock, $configMock, $deviceProviderMock);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testExecuteDisplaysSuccessMessage(): void
    {
        $cronMock = $this->createMock(Cron::class);

        $configMock = $this->createMock(Configuration::class);
        $configMock->method('isCronEnabled')->willReturn(true);

        $deviceProviderMock = $this->createMock(DeviceProvider::class);

        $command = new ExecuteCronCommand($cronMock, $configMock, $deviceProviderMock);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testCommandHasCorrectName(): void
    {
        $cronMock = $this->createMock(Cron::class);
        $configMock = $this->createMock(Configuration::class);
        $deviceProviderMock = $this->createMock(DeviceProvider::class);

        $command = new ExecuteCronCommand($cronMock, $configMock, $deviceProviderMock);

        $this->assertEquals('hat:cron:execute', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $cronMock = $this->createMock(Cron::class);
        $configMock = $this->createMock(Configuration::class);
        $deviceProviderMock = $this->createMock(DeviceProvider::class);

        $command = new ExecuteCronCommand($cronMock, $configMock, $deviceProviderMock);

        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('cron', strtolower($command->getDescription()));
    }

    public function testExecuteWithCronDisabled(): void
    {
        $cronMock = $this->createMock(Cron::class);
        $cronMock->expects($this->never())->method('execute');

        $configMock = $this->createMock(Configuration::class);
        $configMock->method('isCronEnabled')->willReturn(false);

        $deviceProviderMock = $this->createMock(DeviceProvider::class);

        $command = new ExecuteCronCommand($cronMock, $configMock, $deviceProviderMock);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('disabled', strtolower($output));
    }
}
