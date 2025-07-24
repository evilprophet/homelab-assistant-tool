<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Ups;

use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Provider\UpsProvider;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:ups:show-all', description: 'Show list of all UPS')]
class ShowUpsCommand extends Command
{
    public function __construct(
        protected UpsProvider $upsProvider,
        protected Configuration $configuration
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputHelper = new SymfonyStyle($input, $output);

        if (!$this->configuration->isUpsModeEnabled()) {
            $outputHelper->warning('UPS Mode is disabled in configuration.');

            return Command::SUCCESS;
        }

        $this->upsProvider->updateAllUpsStatus();

        $headers = $this->upsProvider->getProperties();
        $upsList = $this->upsProvider->getUpsList();
        $upsArray = array_map(fn($ups) => $ups->toArray(), $upsList);

        try {
            $outputHelper->table($headers, $upsArray);
        } catch (Exception $e) {
            $outputHelper->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
