<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Setup;

use EvilStudio\HAT\Command\Setup\SetupDbCommand;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class SetupDbCommandTest extends TestCase
{
    use \EvilStudio\HAT\Tests\Support\TemporaryPathTrait;

    protected array $capturedBackupSql = [];

    protected function tearDown(): void
    {
        $this->removeTemporaryPaths();

        parent::tearDown();
    }

    public function testExecuteReturnsFailureForMissingDatabaseInMigrateMode(): void
    {
        $filesystem = new Filesystem();
        $applicationDirectory = $this->createTempDirectory();
        $setupCommand = new SetupDbCommand($filesystem, $applicationDirectory, 'var/data/hat.sqlite');

        $tester = new CommandTester($setupCommand);
        $exitCode = $tester->execute(['--migrate' => true], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertMatchesRegularExpression('/does\\s+not\\s+exist/s', $tester->getDisplay());
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

    public function testBackupCopiesDatabaseWhenMigrationsArePending(): void
    {
        $applicationDirectory = $this->createTempDirectory();
        $databasePath = $this->seedDatabase($applicationDirectory);
        $tester = $this->createMigrateTester($applicationDirectory, false);

        $exitCode = $tester->execute(['--migrate' => true, '--backup' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertCount(1, glob($databasePath . '.*.bak') ?: []);
        $this->assertCount(1, $this->capturedBackupSql);
        $this->assertStringStartsWith('VACUUM INTO ', $this->capturedBackupSql[0]);
        $this->assertStringContainsString('Database backed up to', $tester->getDisplay());
    }

    public function testBackupIsSkippedWhenSchemaIsUpToDate(): void
    {
        $applicationDirectory = $this->createTempDirectory();
        $databasePath = $this->seedDatabase($applicationDirectory);
        $tester = $this->createMigrateTester($applicationDirectory, true);

        $exitCode = $tester->execute(['--migrate' => true, '--backup' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame([], glob($databasePath . '.*.bak') ?: []);
        $this->assertSame([], $this->capturedBackupSql);
    }

    public function testMigrateWithoutBackupOptionNeverCopiesTheDatabase(): void
    {
        $applicationDirectory = $this->createTempDirectory();
        $databasePath = $this->seedDatabase($applicationDirectory);
        $tester = $this->createMigrateTester($applicationDirectory, false);

        $exitCode = $tester->execute(['--migrate' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame([], glob($databasePath . '.*.bak') ?: []);
    }

    public function testMigrateRefusesAndNamesTheBaselineCommandForUnmanagedSchema(): void
    {
        $applicationDirectory = $this->createTempDirectory();
        $databasePath = $applicationDirectory . '/var/data/hat.sqlite';
        (new Filesystem())->mkdir(dirname($databasePath));

        $connection = new PDO(sprintf('sqlite:%s', $databasePath));
        $connection->exec('CREATE TABLE devices (id INTEGER PRIMARY KEY)');
        $connection->exec('CREATE TABLE doctrine_migration_versions (version VARCHAR(191) PRIMARY KEY)');

        $tester = $this->createMigrateTester($applicationDirectory, false);
        $exitCode = $tester->execute(['--migrate' => true], ['interactive' => false]);

        $display = $tester->getDisplay();
        $this->assertSame(Command::FAILURE, $exitCode);
        // The console wraps the message, so the assertion must not span a wrap point.
        $this->assertMatchesRegularExpression('/no\s+migration\s+is\s+recorded/s', $display);
        $this->assertStringContainsString('doctrine:migrations:version --add-all', $display);
    }

    public function testMigrateProceedsWhenTheSchemaIsTrackedByMigrations(): void
    {
        $applicationDirectory = $this->createTempDirectory();
        $databasePath = $applicationDirectory . '/var/data/hat.sqlite';
        (new Filesystem())->mkdir(dirname($databasePath));

        $connection = new PDO(sprintf('sqlite:%s', $databasePath));
        $connection->exec('CREATE TABLE devices (id INTEGER PRIMARY KEY)');
        $connection->exec('CREATE TABLE doctrine_migration_versions (version VARCHAR(191) PRIMARY KEY)');
        $connection->exec("INSERT INTO doctrine_migration_versions (version) VALUES ('Version20260228183000')");

        $tester = $this->createMigrateTester($applicationDirectory, true);
        $exitCode = $tester->execute(['--migrate' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    protected function seedDatabase(string $applicationDirectory): string
    {
        $databasePath = $applicationDirectory . '/var/data/hat.sqlite';
        $filesystem = new Filesystem();
        $filesystem->mkdir(dirname($databasePath));
        $filesystem->dumpFile($databasePath, 'sqlite-content');

        return $databasePath;
    }

    protected function createMigrateTester(string $applicationDirectory, bool $upToDate): CommandTester
    {
        $setupCommand = new SetupDbCommand(new Filesystem(), $applicationDirectory, 'var/data/hat.sqlite');

        $application = new Application();
        $application->add($setupCommand);
        $application->add($this->createStubCommand('doctrine:migrations:sync-metadata-storage', true));
        $application->add($this->createStubCommand('doctrine:migrations:up-to-date', $upToDate));
        $application->add($this->createStubCommand('doctrine:migrations:migrate', true, ['allow-no-migration']));
        $application->add($this->createBackupStubCommand($this->capturedBackupSql));

        return new CommandTester($setupCommand);
    }

    protected function createBackupStubCommand(array &$capturedSql): Command
    {
        return new class ('doctrine:query:sql', $capturedSql) extends Command {
            public function __construct(
                string $name,
                protected array &$capturedSql
            ) {
                parent::__construct($name);
                $this->addArgument('sql', InputArgument::REQUIRED);
            }

            protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): int {
                $sql = (string)$input->getArgument('sql');
                $this->capturedSql[] = $sql;

                if (preg_match("/^VACUUM INTO '(.+)'$/", $sql, $matches) === 1) {
                    file_put_contents($matches[1], 'snapshot');
                }

                return Command::SUCCESS;
            }
        };
    }

    protected function createStubCommand(string $name, bool $succeeds, array $options = []): Command
    {
        return new class ($name, $succeeds, $options) extends Command {
            public function __construct(
                string $name,
                protected bool $succeeds,
                protected array $stubOptions
            ) {
                parent::__construct($name);

                foreach ($this->stubOptions as $stubOption) {
                    $this->addOption($stubOption, null, InputOption::VALUE_NONE);
                }
            }

            protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): int {
                return $this->succeeds ? Command::SUCCESS : Command::FAILURE;
            }
        };
    }

    protected function createTempDirectory(): string
    {
        $directory = $this->createTemporaryPath('hat-setup-db-');
        (new Filesystem())->mkdir($directory);

        return $directory;
    }
}
