<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait DestructiveConfirmationTrait
{
    /**
     * Returns null when the caller may proceed, otherwise the exit code to return.
     */
    protected function confirmDestructiveAction(
        InputInterface $input,
        SymfonyStyle $io,
        string $question,
        string $abortMessage
    ): ?int {
        if ($input->getOption('force')) {
            return null;
        }

        // Outside a terminal SymfonyStyle::confirm() silently returns the default
        // instead of prompting, so a missing --force would look like a user abort.
        if (!$input->isInteractive()) {
            $io->error('Confirmation required: pass --force in non-interactive mode.');

            return Command::FAILURE;
        }

        if (!$io->confirm($question, false)) {
            $io->warning($abortMessage);

            return Command::SUCCESS;
        }

        return null;
    }
}
