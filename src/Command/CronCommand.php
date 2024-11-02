<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command;

use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Provider\DeviceProvider;
use EvilStudio\HAT\Service\Cron;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:cron:execute', description: 'Execute cron tasks, it should be added to crontab')]
class CronCommand extends AbstractCommand
{
    public function __construct(
        protected Cron $cron,
        protected Configuration $configuration,
        DeviceProvider $deviceProvider
    )
    {
        parent::__construct($deviceProvider);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputHelper = new SymfonyStyle($input, $output);

        if (!$this->configuration->isCronEnabled()) {
            $outputHelper->warning('Cron is disabled in configuration.');
            return Command::SUCCESS;
        }

        try {
            $this->cron->execute();
        } catch (Exception $e) {
            $outputHelper->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
