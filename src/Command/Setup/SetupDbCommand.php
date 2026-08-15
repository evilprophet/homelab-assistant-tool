<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Setup;

use InvalidArgumentException;
use LogicException;
use PDO;
use PDOException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'hat:setup:db', description: 'Set up database')]
class SetupDbCommand extends Command
{
    protected const string MODE_INIT = 'init';
    protected const string MODE_MIGRATE = 'migrate';
    protected const string BACKUP_TIMESTAMP_FORMAT = 'Ymd-His';
    protected const string BACKUP_SUFFIX = '.bak';

    public function __construct(
        protected Filesystem $filesystem,
        protected string $applicationDirectory,
        protected string $sqliteDatabasePath
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('init', null, InputOption::VALUE_NONE, 'Initialize database and run migrations')
            ->addOption('migrate', null, InputOption::VALUE_NONE, 'Run pending migrations')
            ->addOption(
                'backup',
                null,
                InputOption::VALUE_NONE,
                'Copy the database file before applying migrations, skipped when the schema is already up to date'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $mode = $this->resolveMode($input, $io);

        if ($mode === null) {
            return Command::FAILURE;
        }

        $databasePath = $this->resolveAbsoluteDatabasePath();
        $databaseExists = $this->filesystem->exists($databasePath);

        if ($mode === self::MODE_MIGRATE && !$databaseExists) {
            $io->error(
                sprintf(
                    "Database file '%s' does not exist. Run 'hat:setup:db --init' to create it first.",
                    $databasePath
                )
            );

            return Command::FAILURE;
        }

        if ($mode === self::MODE_INIT) {
            if ($databaseExists) {
                if (!$input->isInteractive()) {
                    $io->error(
                        sprintf(
                            "Database file '%s' already exists. Run interactively to confirm --init.",
                            $databasePath
                        )
                    );

                    return Command::FAILURE;
                }

                if (!$io->confirm(sprintf("Database file '%s' already exists. Continue?", $databasePath), false)) {
                    $io->warning('Database setup aborted by user.');

                    return Command::SUCCESS;
                }
            } else {
                $this->filesystem->mkdir(dirname($databasePath));
                $this->filesystem->touch($databasePath);
                $io->note(sprintf("Created SQLite database file: '%s'.", $databasePath));
            }
        }

        if (!$this->runDoctrineCommand('doctrine:migrations:sync-metadata-storage', $input, $output)) {
            return Command::FAILURE;
        }

        if ($this->hasUnmanagedSchema($databasePath)) {
            $io->error(
                sprintf(
                    "Database '%s' already contains application tables, but no migration is recorded as applied. "
                    . 'Migrating would fail with "table already exists".',
                    $databasePath
                )
            );
            $io->writeln('Baseline the existing schema first, then re-run this command:');
            $io->writeln('    bin/console doctrine:migrations:version --add-all --no-interaction');

            return Command::FAILURE;
        }

        if ((bool)$input->getOption('backup') && !$this->backupWhenMigrationsArePending($databasePath, $input, $io)) {
            return Command::FAILURE;
        }

        if (
            !$this->runDoctrineCommand(
                'doctrine:migrations:migrate',
                $input,
                $output,
                [
                    '--allow-no-migration' => true,
                    '--no-interaction' => true,
                ]
            )
        ) {
            return Command::FAILURE;
        }

        $io->success('Database setup completed.');

        return Command::SUCCESS;
    }

    protected function resolveMode(InputInterface $input, SymfonyStyle $io): ?string
    {
        $init = (bool)$input->getOption('init');
        $migrate = (bool)$input->getOption('migrate');

        if ($init && $migrate) {
            $io->error('Use either --init or --migrate, not both.');

            return null;
        }

        if ($init) {
            return self::MODE_INIT;
        }

        if ($migrate) {
            return self::MODE_MIGRATE;
        }

        if (!$input->isInteractive()) {
            $io->error('No mode selected. Use --init or --migrate.');

            return null;
        }

        return $io->choice('Select setup mode', [self::MODE_INIT, self::MODE_MIGRATE], self::MODE_INIT);
    }

    protected function backupWhenMigrationsArePending(
        string $databasePath,
        InputInterface $input,
        SymfonyStyle $io
    ): bool {
        if ($this->runDoctrineCommand('doctrine:migrations:up-to-date', $input, new NullOutput())) {
            return true;
        }

        $backupPath = sprintf('%s.%s%s', $databasePath, date(self::BACKUP_TIMESTAMP_FORMAT), self::BACKUP_SUFFIX);

        // A file copy is not a valid backup under WAL, and the cron process may write
        // mid-copy. VACUUM INTO produces a consistent snapshot of a live database.
        $backupSucceeded = $this->runDoctrineCommand(
            'doctrine:query:sql',
            $input,
            new NullOutput(),
            ['sql' => sprintf("VACUUM INTO '%s'", str_replace("'", "''", $backupPath))]
        );

        if (!$backupSucceeded) {
            $io->error(sprintf("Cannot create database backup at '%s'.", $backupPath));

            return false;
        }

        $io->note(sprintf("Database backed up to '%s' before migrating.", $backupPath));

        return true;
    }

    /**
     * Plain CREATE TABLE in the initial migration means a schema created outside the
     * migration flow (schema:create, a restore without the metadata table) leaves the
     * command unable to migrate, with no hint about the recovery command.
     */
    protected function hasUnmanagedSchema(string $databasePath): bool
    {
        if (!$this->filesystem->exists($databasePath)) {
            return false;
        }

        try {
            $connection = new PDO(sprintf('sqlite:%s', $databasePath));
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $applicationTables = (int)$connection
                ->query(
                    "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name IN "
                    . "('devices', 'ups', 'schedules', 'action_logs', 'users')"
                )
                ->fetchColumn();

            if ($applicationTables === 0) {
                return false;
            }

            $appliedMigrations = (int)$connection
                ->query('SELECT COUNT(*) FROM doctrine_migration_versions')
                ->fetchColumn();

            return $appliedMigrations === 0;
        } catch (PDOException) {
            // An unreadable file or a missing metadata table is not something this
            // check should decide on; let the migration itself report the problem.
            return false;
        }
    }

    protected function resolveAbsoluteDatabasePath(): string
    {
        // doctrine.yaml prefixes the project dir unconditionally, so an absolute value
        // would make this command and the application use two different files.
        if (str_starts_with($this->sqliteDatabasePath, '/')) {
            throw new InvalidArgumentException(
                sprintf(
                    "sqlite_database_path must be relative to the project root, got '%s'.",
                    $this->sqliteDatabasePath
                )
            );
        }

        return sprintf('%s/%s', $this->applicationDirectory, $this->sqliteDatabasePath);
    }

    protected function runDoctrineCommand(
        string $commandName,
        InputInterface $input,
        OutputInterface $output,
        array $arguments = []
    ): bool {
        $application = $this->getApplication();
        if ($application === null) {
            throw new LogicException('Console application is not available.');
        }

        $command = $application->find($commandName);
        $commandInput = new ArrayInput(array_merge(['command' => $commandName], $arguments));
        $commandInput->setInteractive($input->isInteractive());

        return $command->run($commandInput, $output) === Command::SUCCESS;
    }
}
