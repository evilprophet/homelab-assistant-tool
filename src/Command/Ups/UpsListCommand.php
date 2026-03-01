<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Ups;

use EvilStudio\HAT\Service\Runtime\UpsRuntimeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'hat:ups:list', description: 'List UPS')]
class UpsListCommand extends Command
{
    public function __construct(
        protected UpsRuntimeService $upsRuntimeService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $runtimeUpsList = $this->upsRuntimeService->listRuntimeUps();
        if (empty($runtimeUpsList)) {
            $io->note('No UPS entries found.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($runtimeUpsList as $runtimeUps) {
            try {
                $runtimeUps->updateStatus();
            } catch (Throwable) {
            }

            $rows[] = $runtimeUps->toArray();
        }

        $io->table(
            ['ID', 'Name', 'Identifier', 'Model Name', 'Serial Number', 'Status', 'Power', 'Battery', 'Linked Device'],
            $rows
        );

        return Command::SUCCESS;
    }
}
