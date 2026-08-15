<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Setup;

use EvilStudio\HAT\Command\Setup\SetupInitCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Tester\CommandTester;

class SetupInitCommandTest extends TestCase
{
    public function testExecuteRunsConfigureAndDatabaseSteps(): void
    {
        $setupInitCommand = new SetupInitCommand();
        $configureRuns = [];
        $dbRuns = [];
        $configureCommand = new class ('hat:setup:configure', $configureRuns, Command::SUCCESS) extends Command {
            public function __construct(
                string $name,
                protected array &$runs,
                protected int $exitCode
            ) {
                parent::__construct($name);
            }

            protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): int {
                $this->runs[] = $input->getArguments();

                return $this->exitCode;
            }
        };
        $dbCommand = $this->createDatabaseStubCommand($dbRuns, Command::SUCCESS);

        $application = new Application();
        $application->add($setupInitCommand);
        $application->add($configureCommand);
        $application->add($dbCommand);

        $tester = new CommandTester($setupInitCommand);
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertCount(1, $configureRuns);
        $this->assertCount(1, $dbRuns);
        $this->assertTrue($dbRuns[0]['init']);
        $this->assertStringContainsString('Setup init completed.', $tester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenConfigureStepFails(): void
    {
        $setupInitCommand = new SetupInitCommand();
        $configureRuns = [];
        $dbRuns = [];
        $configureCommand = new class ('hat:setup:configure', $configureRuns, Command::FAILURE) extends Command {
            public function __construct(
                string $name,
                protected array &$runs,
                protected int $exitCode
            ) {
                parent::__construct($name);
            }

            protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): int {
                $this->runs[] = $input->getArguments();

                return $this->exitCode;
            }
        };
        $dbCommand = $this->createDatabaseStubCommand($dbRuns, Command::SUCCESS);

        $application = new Application();
        $application->add($setupInitCommand);
        $application->add($configureCommand);
        $application->add($dbCommand);

        $tester = new CommandTester($setupInitCommand);
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertCount(1, $configureRuns);
        $this->assertCount(0, $dbRuns);
        $this->assertStringContainsString('Setup failed during configuration step.', $tester->getDisplay());
    }

    protected function createDatabaseStubCommand(array &$runs, int $exitCode): Command
    {
        return new class ('hat:setup:db', $runs, $exitCode) extends Command {
            public function __construct(
                string $name,
                protected array &$runs,
                protected int $exitCode
            ) {
                parent::__construct($name);
                $this->addOption('init', null, InputOption::VALUE_NONE);
            }

            protected function execute(
                \Symfony\Component\Console\Input\InputInterface $input,
                \Symfony\Component\Console\Output\OutputInterface $output
            ): int {
                $this->runs[] = $input->getArguments() + $input->getOptions();

                return $this->exitCode;
            }
        };
    }
}
