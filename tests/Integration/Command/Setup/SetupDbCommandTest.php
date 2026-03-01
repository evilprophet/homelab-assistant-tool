<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Setup;

use EvilStudio\HAT\Command\Setup\SetupDbCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class SetupDbCommandTest extends TestCase
{
    public function testExecuteReturnsFailureForMissingDatabaseInMigrateMode(): void
    {
        $filesystem = new Filesystem();
        $applicationDirectory = $this->createTempDirectory();
        $setupCommand = new SetupDbCommand($filesystem, $applicationDirectory, 'var/data/hat.sqlite');

        $tester = new CommandTester($setupCommand);
        $exitCode = $tester->execute(['--migrate' => true], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertMatchesRegularExpression('/does\\s+not exist/s', $tester->getDisplay());
    }

    public function testExecuteCreatesDatabaseAndRunsDoctrineCommandsForInitMode(): void
    {
        $filesystem = new Filesystem();
        $applicationDirectory = $this->createTempDirectory();

        $setupCommand = new SetupDbCommand($filesystem, $applicationDirectory, 'var/data/hat.sqlite');
        $syncRuns = [];
        $migrateRuns = [];
        $syncCommand = new class ('doctrine:migrations:sync-metadata-storage', $syncRuns) extends Command {
            public function __construct(
                string $name,
                protected array &$runs
            ) {
                parent::__construct($name);
            }

            protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): int {
                $this->runs[] = $input->getArguments();

                return Command::SUCCESS;
            }
        };
        $migrateCommand = new class ('doctrine:migrations:migrate', $migrateRuns) extends Command {
            public function __construct(
                string $name,
                protected array &$runs
            ) {
                parent::__construct($name);
                $this->addOption('allow-no-migration', null, InputOption::VALUE_NONE);
            }

            protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): int {
                $this->runs[] = $input->getArguments() + $input->getOptions();

                return Command::SUCCESS;
            }
        };

        $application = new Application();
        $application->add($setupCommand);
        $application->add($syncCommand);
        $application->add($migrateCommand);

        $tester = new CommandTester($setupCommand);
        $exitCode = $tester->execute(['--init' => true], ['interactive' => false]);

        $databasePath = $applicationDirectory . '/var/data/hat.sqlite';
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($databasePath);
        $this->assertCount(1, $syncRuns);
        $this->assertCount(1, $migrateRuns);
    }

    public function testExecuteReturnsFailureWhenModeFlagsConflict(): void
    {
        $filesystem = new Filesystem();
        $applicationDirectory = $this->createTempDirectory();
        $setupCommand = new SetupDbCommand($filesystem, $applicationDirectory, 'var/data/hat.sqlite');

        $tester = new CommandTester($setupCommand);
        $exitCode = $tester->execute(['--init' => true, '--migrate' => true], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Use either --init or --migrate, not both.', $tester->getDisplay());
    }

    protected function createTempDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/hat-setup-db-' . uniqid('', true);
        (new Filesystem())->mkdir($directory);

        return $directory;
    }
}
