<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\User;

use EvilStudio\HAT\Command\Support\DestructiveConfirmationTrait;
use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use Throwable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:user:remove', description: 'Remove authentication user for simple auth mode')]
class UserRemoveCommand extends Command
{
    use DestructiveConfirmationTrait;

    public function __construct(
        protected AuthModeResolver $authModeResolver,
        protected AuthUserService $authUserService,
        protected ActionLogService $actionLogService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'Username')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->authModeResolver->isSimpleMode()) {
            $io->error(
                sprintf(
                    "Command 'hat:user:remove' is disabled for auth mode '%s'. Set HAT_AUTH_MODE=simple to enable it.",
                    $this->authModeResolver->getMode()
                )
            );

            return Command::FAILURE;
        }

        $username = trim((string)$input->getArgument('username'));
        if ($username === '') {
            if (!$input->isInteractive()) {
                $io->error("Argument 'username' is required.");

                return Command::FAILURE;
            }

            $username = trim((string)$io->ask('Username'));
            if ($username === '') {
                $io->error('Username cannot be empty.');

                return Command::FAILURE;
            }
        }

        $confirmationExitCode = $this->confirmDestructiveAction(
            $input,
            $io,
            sprintf("Remove user '%s'?", $username),
            'User removal aborted by user.'
        );
        if ($confirmationExitCode !== null) {
            return $confirmationExitCode;
        }

        try {
            $this->authUserService->removeSimpleUser($username);
            $this->safeCreateCliLog(
                ActionLogAction::USER_REMOVE,
                ActionLog::LEVEL_WARNING,
                sprintf("User '%s' removed.", $username)
            );
            $io->success(sprintf("User '%s' removed.", $username));

            return Command::SUCCESS;
        } catch (EntityNotFound $exception) {
            $this->safeCreateCliLog(ActionLogAction::USER_REMOVE, ActionLog::LEVEL_WARNING, $exception->getMessage());
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $this->safeCreateCliLog(ActionLogAction::USER_REMOVE, ActionLog::LEVEL_ERROR, $exception->getMessage());
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    protected function safeCreateCliLog(ActionLogAction $action, string $level, string $message): void
    {
        try {
            $this->actionLogService->createActionLog(ActionLog::SOURCE_CLI, $action->value, $level, $message);
        } catch (Throwable) {
        }
    }
}
