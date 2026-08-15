<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Setup;

use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'hat:setup:configure', description: 'Configure app')]
class SetupConfigureCommand extends Command
{
    protected const string DEFAULT_TIMEZONE = 'UTC';
    protected const string DEFAULT_SSH_KEY_PATH = 'var/data/id_ed25519';
    protected const string DEFAULT_SSH_USERNAME = 'root';
    protected const string DEFAULT_SQLITE_DATABASE_PATH = 'var/data/hat.sqlite';
    protected const int DEFAULT_ACTION_LOG_RETENTION_DAYS = 90;

    public function __construct(
        protected Filesystem $filesystem,
        protected string $applicationDirectory
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $configPath = $this->getConfigPath();
        $existingConfig = $this->readExistingConfig($configPath, $io);

        if ($this->filesystem->exists($configPath)) {
            if (!$input->isInteractive()) {
                $io->error('Configuration file already exists. Run this command interactively to confirm overwrite.');

                return Command::FAILURE;
            }

            if (!$io->confirm(sprintf("Configuration file '%s' already exists. Overwrite it?", $configPath), false)) {
                $io->warning('Setup configuration aborted by user.');

                return Command::SUCCESS;
            }
        }

        $configuration = $existingConfig['configuration'] ?? [];
        $cronEnabled = $io->confirm('Enable cron mode?', (bool)($configuration['cron'] ?? true));
        $upsModeEnabled = $io->confirm('Enable UPS mode?', (bool)($configuration['ups_mode'] ?? true));
        $timezone = $io->ask(
            'Timezone',
            (string)($configuration['timezone'] ?? self::DEFAULT_TIMEZONE),
            static function (mixed $value): string {
                $timezone = trim((string)$value);
                if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                    throw new InvalidArgumentException(
                        sprintf("'%s' is not a known timezone identifier, for example Europe/Warsaw.", $timezone)
                    );
                }

                return $timezone;
            }
        );
        $sshKeyPath = $io->ask(
            'SSH private key path',
            (string)($configuration['ssh_key_path'] ?? self::DEFAULT_SSH_KEY_PATH)
        );
        $defaultSshUsername = $io->ask(
            'Default SSH username',
            (string)($configuration['default_ssh_username'] ?? self::DEFAULT_SSH_USERNAME)
        );
        $sqliteDatabasePath = $io->ask(
            'SQLite database path (relative to project root)',
            (string)($existingConfig['sqlite_database_path'] ?? self::DEFAULT_SQLITE_DATABASE_PATH),
            static function (mixed $value): string {
                $path = trim((string)$value);
                if ($path === '' || str_starts_with($path, '/')) {
                    throw new InvalidArgumentException(
                        'Database path must be relative to the project root, for example var/data/hat.sqlite.'
                    );
                }

                return $path;
            }
        );
        $actionLogDefaultRetentionDays = (int)$io->ask(
            'Default action log retention in days',
            (string)(
                $configuration['action_log_retention_days']
                ?? self::DEFAULT_ACTION_LOG_RETENTION_DAYS
            ),
            static function (mixed $value): int {
                $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($parsed === false) {
                    throw new InvalidArgumentException('Action log retention days must be a positive integer.');
                }

                return (int)$parsed;
            }
        );

        $parameters = [
            'sqlite_database_path' => $sqliteDatabasePath,
            'configuration' => [
                'cron' => $cronEnabled,
                'ups_mode' => $upsModeEnabled,
                'ssh_key_path' => $sshKeyPath,
                'default_ssh_username' => $defaultSshUsername,
                'timezone' => $timezone,
                'action_log_retention_days' => $actionLogDefaultRetentionDays,
            ],
        ];

        $yaml = Yaml::dump(['parameters' => $parameters], 4, 2);
        $yaml = rtrim($yaml, "\n") . "\n";

        $this->filesystem->mkdir(dirname($configPath));
        $this->filesystem->dumpFile($configPath, $yaml);

        $io->success(sprintf("Configuration saved to '%s'.", $configPath));

        return Command::SUCCESS;
    }

    protected function getConfigPath(): string
    {
        return sprintf('%s/config/parameters.yaml', $this->applicationDirectory);
    }

    protected function readExistingConfig(string $configPath, SymfonyStyle $io): array
    {
        if (!$this->filesystem->exists($configPath)) {
            return [];
        }

        try {
            $parsed = Yaml::parseFile($configPath);
        } catch (ParseException $exception) {
            // Repairing a broken file is exactly why this command gets run, so an
            // unreadable one must fall back to defaults instead of aborting.
            $io->warning(
                sprintf(
                    "Existing configuration '%s' is not valid YAML (%s). Continuing with defaults.",
                    $configPath,
                    $exception->getMessage()
                )
            );

            return [];
        }

        if (!is_array($parsed)) {
            return [];
        }

        $parameters = $parsed['parameters'] ?? [];

        return is_array($parameters) ? $parameters : [];
    }
}
