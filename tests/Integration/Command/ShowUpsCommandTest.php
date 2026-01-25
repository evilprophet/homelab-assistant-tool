<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command;

use EvilStudio\HAT\Api\UpsInterface;
use EvilStudio\HAT\Command\Ups\ShowUpsCommand;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Provider\UpsProvider;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ShowUpsCommandTest extends TestCase
{
    protected function createCommand(bool $upsModeEnabled = true, array $upsList = []): ShowUpsCommand
    {
        $configMock = $this->createMock(Configuration::class);
        $configMock->method('isUpsModeEnabled')->willReturn($upsModeEnabled);

        $upsProviderMock = $this->createMock(UpsProvider::class);
        $upsProviderMock->method('getUpsList')->willReturn($upsList);
        $upsProviderMock->method('getProperties')->willReturn(['Name', 'Status', 'Battery']);

        return new ShowUpsCommand($upsProviderMock, $configMock);
    }

    public function testConstructor(): void
    {
        $command = $this->createCommand();

        $this->assertInstanceOf(ShowUpsCommand::class, $command);
    }

    public function testExecuteWithUpsModeDisabled(): void
    {
        $command = $this->createCommand(upsModeEnabled: false);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('disabled', strtolower($output));
        $this->assertStringContainsString('warning', strtolower($output));
    }

    public function testExecuteWithEmptyUpsList(): void
    {
        $command = $this->createCommand(upsModeEnabled: true, upsList: []);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Status', $output);
    }

    public function testExecuteWithSingleUps(): void
    {
        $upsMock = $this->createMock(UpsInterface::class);
        $upsMock->method('toArray')->willReturn([
            'identifier' => 'ups1',
            'status' => 'online',
            'battery_charge' => 100,
            'battery_runtime' => 3600,
        ]);

        $command = $this->createCommand(upsModeEnabled: true, upsList: [$upsMock]);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('ups1', $output);
        $this->assertStringContainsString('online', $output);
    }

    public function testExecuteWithMultipleUps(): void
    {
        $upsMock1 = $this->createMock(UpsInterface::class);
        $upsMock1->method('toArray')->willReturn([
            'identifier' => 'ups1',
            'status' => 'online',
        ]);

        $upsMock2 = $this->createMock(UpsInterface::class);
        $upsMock2->method('toArray')->willReturn([
            'identifier' => 'ups2',
            'status' => 'on battery',
        ]);

        $command = $this->createCommand(upsModeEnabled: true, upsList: [$upsMock1, $upsMock2]);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('ups1', $output);
        $this->assertStringContainsString('ups2', $output);
    }

    public function testExecuteDisplaysTableFormat(): void
    {
        $upsMock = $this->createMock(UpsInterface::class);
        $upsMock->method('toArray')->willReturn([
            'identifier' => 'test-ups',
            'status' => 'online',
        ]);

        $command = $this->createCommand(upsModeEnabled: true, upsList: [$upsMock]);
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('-', $output);
        $this->assertStringContainsString('Name', $output);
    }

    public function testCommandHasCorrectName(): void
    {
        $command = $this->createCommand();

        $this->assertEquals('hat:ups:show-all', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $command = $this->createCommand();

        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('ups', strtolower($command->getDescription()));
    }
}
