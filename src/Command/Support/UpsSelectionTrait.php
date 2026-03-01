<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Support;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait UpsSelectionTrait
{
    protected function resolveUpsId(
        InputInterface $input,
        SymfonyStyle $io,
        string $question = 'UPS',
        string $emptyListMessage = 'No UPS entries available.',
        bool $requireArgumentWhenNonInteractive = true
    ): int|false {
        $idArgument = $input->getArgument('id');
        if (is_string($idArgument) && trim($idArgument) !== '') {
            $normalized = filter_var($idArgument, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($normalized === false) {
                $io->error('id must be a positive integer.');

                return false;
            }

            return (int)$normalized;
        }

        if ($requireArgumentWhenNonInteractive && !$input->isInteractive()) {
            $io->error("Argument 'id' is required.");

            return false;
        }

        $upsChoices = $this->buildUpsChoices();
        if (empty($upsChoices)) {
            $io->error($emptyListMessage);

            return false;
        }

        $selected = $io->choice($question, array_keys($upsChoices));

        return (int)$upsChoices[$selected];
    }

    protected function promptUpsId(SymfonyStyle $io, ?int $defaultUpsId = null): int|false|null
    {
        $upsChoices = $this->buildUpsChoices();
        if (empty($upsChoices)) {
            return null;
        }

        $choicesWithNone = ['None' => null] + $upsChoices;
        $defaultChoice = 'None';
        foreach ($choicesWithNone as $label => $upsId) {
            if ($upsId === $defaultUpsId) {
                $defaultChoice = $label;
                break;
            }
        }

        $selected = $io->choice('UPS', array_keys($choicesWithNone), $defaultChoice);
        $selectedUpsId = $choicesWithNone[$selected] ?? null;

        return $selectedUpsId === null ? null : (int)$selectedUpsId;
    }

    protected function buildUpsChoices(): array
    {
        $upsCollection = $this->upsService->listUps();
        if (empty($upsCollection)) {
            return [];
        }

        $choices = [];
        foreach ($upsCollection as $ups) {
            $upsId = $ups->getId();
            if ($upsId === null) {
                continue;
            }

            $label = sprintf('%d: %s (%s)', $upsId, $ups->getName(), $ups->getIdentifier());
            $choices[$label] = $upsId;
        }

        return $choices;
    }
}
