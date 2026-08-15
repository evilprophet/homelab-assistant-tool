<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Setup;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:setup:init', description: 'Initialize app')]
class SetupInitCommand extends Command
{
    protected const string CONFIGURE_COMMAND_NAME = 'hat:setup:configure';
    protected const string SETUP_DB_COMMAND_NAME = 'hat:setup:db';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('HAT setup initialization');

        if (!$this->runCommand(self::CONFIGURE_COMMAND_NAME, $input, $output)) {
            $io->error('Setup failed during configuration step.');

            return Command::FAILURE;
        }

        // hat:setup:db refuses to guess a mode when non-interactive, and init is the
        // only mode this flow can mean.
        if (!$this->runCommand(self::SETUP_DB_COMMAND_NAME, $input, $output, ['--init' => true])) {
            $io->error('Setup failed during database step.');

            return Command::FAILURE;
        }

        $io->success('Setup init completed.');

        return Command::SUCCESS;
    }

    protected function runCommand(
        string $commandName,
        InputInterface $input,
        OutputInterface $output,
        array $options = []
    ): bool {
        $application = $this->getApplication();
        if ($application === null) {
            return false;
        }

        $command = $application->find($commandName);
        $commandInput = new ArrayInput([
            'command' => $commandName,
        ] + $options);
        $commandInput->setInteractive($input->isInteractive());

        return $command->run($commandInput, $output) === Command::SUCCESS;
    }
}
