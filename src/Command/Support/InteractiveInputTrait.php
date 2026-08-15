<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Support;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait InteractiveInputTrait
{
    // Symfony returns the default value on empty input, so pressing Enter can never
    // clear a field that already has one. This sentinel gives the prompts a way to.
    protected const string CLEAR_SENTINEL = '-';

    protected function resolveRequiredArgument(
        InputInterface $input,
        SymfonyStyle $io,
        string $argumentName,
        string $question
    ): ?string {
        $argument = $input->getArgument($argumentName);
        if (is_string($argument) && trim($argument) !== '') {
            return trim($argument);
        }

        if (!$input->isInteractive()) {
            $io->error(sprintf("Argument '%s' is required.", $argumentName));

            return null;
        }

        $value = trim((string)$io->ask($question));
        if ($value === '') {
            $io->error(sprintf("Argument '%s' cannot be empty.", $argumentName));

            return null;
        }

        return $value;
    }

    protected function resolveStringOption(
        InputInterface $input,
        SymfonyStyle $io,
        string $optionName,
        string $question,
        string $defaultValue
    ): ?string {
        if ($input->hasParameterOption(sprintf('--%s', $optionName))) {
            $value = trim((string)$input->getOption($optionName));
            if ($value === '') {
                $io->error(sprintf("Option --%s cannot be empty.", $optionName));

                return null;
            }

            return $value;
        }

        if ($input->isInteractive()) {
            $value = trim((string)$io->ask($question, $defaultValue));
            if ($value === '') {
                $io->error(sprintf("Value for '%s' cannot be empty.", $question));

                return null;
            }

            return $value;
        }

        return $defaultValue;
    }

    protected function parsePositiveInt(string $value, string $name, SymfonyStyle $io): int|false
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($normalized === false) {
            $io->error(sprintf('%s must be a positive integer.', $name));

            return false;
        }

        return (int)$normalized;
    }

    protected function parseOptionalPositiveInt(mixed $value, string $optionName, SymfonyStyle $io): int|false|null
    {
        if ($value === null) {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($normalized === false) {
            $io->error(sprintf("Option %s must be a positive integer.", $optionName));

            return false;
        }

        return (int)$normalized;
    }

    protected function parseOptionalNonNegativeInt(mixed $value, string $optionName, SymfonyStyle $io): int|false|null
    {
        if ($value === null) {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($normalized === false) {
            $io->error(sprintf("Option %s must be a non-negative integer.", $optionName));

            return false;
        }

        return (int)$normalized;
    }

    protected function promptOptionalNonNegativeInt(
        SymfonyStyle $io,
        string $question,
        ?int $defaultValue
    ): int|false|null {
        $rawValue = $io->ask($question, $defaultValue === null ? '' : (string)$defaultValue);
        $value = trim((string)$rawValue);

        if ($value === '' || $value === self::CLEAR_SENTINEL) {
            return null;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($normalized === false) {
            $io->error(sprintf("Value for '%s' must be a non-negative integer.", $question));

            return false;
        }

        return (int)$normalized;
    }

    protected function promptOptionalString(SymfonyStyle $io, string $question, ?string $defaultValue): ?string
    {
        $value = trim((string)$io->ask($question, $defaultValue ?? ''));

        return $value === self::CLEAR_SENTINEL ? null : $this->nullIfEmpty($value);
    }

    protected function nullIfEmpty(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
