<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Support;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait PasswordInputTrait
{
    protected const string STDIN_OPTION = 'password-stdin';
    protected const string PLAIN_OPTION = 'password';

    protected function resolvePassword(
        InputInterface $input,
        SymfonyStyle $io,
        string $question,
        string $confirmationQuestion
    ): string|false {
        if ($input->getOption(self::STDIN_OPTION)) {
            return $this->readPasswordFromStandardInput($io);
        }

        if ($input->hasOption(self::PLAIN_OPTION) && $input->hasParameterOption('--' . self::PLAIN_OPTION)) {
            return (string)$input->getOption(self::PLAIN_OPTION);
        }

        if (!$input->isInteractive()) {
            $io->error(
                $input->hasOption(self::PLAIN_OPTION)
                    ? 'Option --password-stdin or --password is required in non-interactive mode.'
                    : 'Option --password-stdin is required in non-interactive mode.'
            );

            return false;
        }

        $password = (string)$io->askHidden($question);
        if ($password !== (string)$io->askHidden($confirmationQuestion)) {
            $io->error('Password confirmation does not match.');

            return false;
        }

        return $password;
    }

    protected function readPasswordFromStandardInput(SymfonyStyle $io): string|false
    {
        $rawPassword = $this->readStandardInput();
        if ($rawPassword === false) {
            $io->error('Unable to read the password from standard input.');

            return false;
        }

        // Only the line ending a shell appends is stripped; every other whitespace
        // character is rejected by the service instead of being silently removed.
        return (string)preg_replace('/\r?\n\z/', '', $rawPassword);
    }

    protected function readStandardInput(): string|false
    {
        return file_get_contents('php://stdin');
    }
}
