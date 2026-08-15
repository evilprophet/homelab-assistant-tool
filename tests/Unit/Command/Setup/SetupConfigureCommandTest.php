<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Setup;

use EvilStudio\HAT\Command\Setup\SetupConfigureCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class SetupConfigureCommandTest extends TestCase
{
    use \EvilStudio\HAT\Tests\Support\TemporaryPathTrait;

    protected function tearDown(): void
    {
        $this->removeTemporaryPaths();

        parent::tearDown();
    }
    public function testExecuteCreatesConfigurationFileWithProvidedValues(): void
    {
        $filesystem = new Filesystem();
        $applicationDirectory = $this->createTempDirectory();

        $command = new SetupConfigureCommand($filesystem, $applicationDirectory);
        $tester = new CommandTester($command);
        $tester->setInputs([
            'yes',
            'no',
            'Europe/Warsaw',
            '/tmp/id_ed25519',
            'admin',
            'var/data/app.sqlite',
            '120',
        ]);

        $exitCode = $tester->execute([], ['interactive' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $configPath = $applicationDirectory . '/config/parameters.yaml';
        $this->assertFileExists($configPath);

        $parsed = Yaml::parseFile($configPath);
        $parameters = $parsed['parameters'] ?? [];

        $this->assertSame('var/data/app.sqlite', $parameters['sqlite_database_path'] ?? null);
        $this->assertTrue((bool)($parameters['configuration']['cron'] ?? false));
        $this->assertFalse((bool)($parameters['configuration']['ups_mode'] ?? true));
        $this->assertSame('Europe/Warsaw', $parameters['configuration']['timezone'] ?? null);
        $this->assertSame('/tmp/id_ed25519', $parameters['configuration']['ssh_key_path'] ?? null);
        $this->assertSame('admin', $parameters['configuration']['default_ssh_username'] ?? null);
        $this->assertSame(120, $parameters['configuration']['action_log_retention_days'] ?? null);
    }

    public function testExecuteReturnsFailureWhenConfigExistsInNonInteractiveMode(): void
    {
        $filesystem = new Filesystem();
        $applicationDirectory = $this->createTempDirectory();
        $configPath = $applicationDirectory . '/config/parameters.yaml';
        $filesystem->mkdir(dirname($configPath));
        $filesystem->dumpFile($configPath, "parameters:\n  sqlite_database_path: var/data/hat.sqlite\n");

        $tester = new CommandTester(new SetupConfigureCommand($filesystem, $applicationDirectory));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Configuration file already exists.', $tester->getDisplay());
    }

    public function testExecuteRepairsConfigurationFileThatIsNotValidYaml(): void
    {
        $filesystem = new Filesystem();
        $applicationDirectory = $this->createTempDirectory();
        $configPath = $applicationDirectory . '/config/parameters.yaml';
        $filesystem->mkdir(dirname($configPath));
        $filesystem->dumpFile($configPath, "parameters:\n  configuration:\n   cron: [unclosed\n");

        $tester = new CommandTester(new SetupConfigureCommand($filesystem, $applicationDirectory));
        $tester->setInputs([
            'yes',
            'yes',
            'no',
            'UTC',
            'var/data/id_ed25519',
            'root',
            'var/data/hat.sqlite',
            '90',
        ]);
        $exitCode = $tester->execute([], ['interactive' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('is not valid YAML', $tester->getDisplay());

        $parsed = Yaml::parseFile($configPath);
        $this->assertSame('var/data/hat.sqlite', $parsed['parameters']['sqlite_database_path'] ?? null);
    }

    protected function createTempDirectory(): string
    {
        $directory = $this->createTemporaryPath('hat-setup-config-');
        (new Filesystem())->mkdir($directory);

        return $directory;
    }
}
