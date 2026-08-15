<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\Ups;

use EvilStudio\HAT\Command\Support\RuntimeTableRowTrait;
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
    use RuntimeTableRowTrait;

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
        $unreachableIdentifiers = [];
        foreach ($runtimeUpsList as $runtimeUps) {
            try {
                $runtimeUps->updateStatus();
            } catch (Throwable) {
                $unreachableIdentifiers[] = $runtimeUps->getIdentifier();
            }

            $row = $runtimeUps->toArray();
            $row['linked_devices'] = $this->formatDeviceList($row['linked_devices'] ?? []);
            $rows[] = $row;
        }

        if (!empty($unreachableIdentifiers)) {
            $io->warning(
                sprintf(
                    'Status could not be read for: %s. Those rows show no live data.',
                    implode(', ', $unreachableIdentifiers)
                )
            );
        }

        $io->table(
            ['ID', 'Name', 'Identifier', 'Model Name', 'Serial Number', 'Status', 'Power', 'Battery', 'Linked Devices'],
            $rows
        );

        return Command::SUCCESS;
    }
}
