<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Setup;

use LogicException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'hat:setup:db', description: 'Set up database')]
class SetupDbCommand extends Command
{
    protected const string MODE_INIT = 'init';
    protected const string MODE_MIGRATE = 'migrate';

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
            ->addOption('migrate', null, InputOption::VALUE_NONE, 'Run pending migrations');
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

    protected function resolveAbsoluteDatabasePath(): string
    {
        if (str_starts_with($this->sqliteDatabasePath, '/')) {
            return $this->sqliteDatabasePath;
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
