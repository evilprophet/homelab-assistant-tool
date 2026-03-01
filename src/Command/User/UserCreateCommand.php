<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\User;

use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use Throwable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hat:user:create', description: 'Create authentication user for simple auth mode')]
class UserCreateCommand extends Command
{
    public function __construct(
        protected AuthModeResolver $authModeResolver,
        protected AuthUserService $authUserService,
        protected ActionLogService $actionLogService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->authModeResolver->isSimpleMode()) {
            $io->warning(
                sprintf(
                    "Command 'hat:user:create' is disabled for auth mode '%s'. Set HAT_AUTH_MODE=simple to enable it.",
                    $this->authModeResolver->getMode()
                )
            );

            return Command::SUCCESS;
        }

        $username = trim((string)$io->ask('Username'));
        if ($username === '') {
            $io->error('Username cannot be empty.');

            return Command::FAILURE;
        }

        $password = trim((string)$io->askHidden('Password'));
        $passwordConfirm = trim((string)$io->askHidden('Confirm password'));
        if ($password === '' || $passwordConfirm === '') {
            $io->error('Password cannot be empty.');

            return Command::FAILURE;
        }

        if ($password !== $passwordConfirm) {
            $io->error('Password confirmation does not match.');

            return Command::FAILURE;
        }

        try {
            $user = $this->authUserService->createSimpleUser($username, $password);
            $this->safeCreateCliLog(
                ActionLogAction::USER_CREATE,
                ActionLog::LEVEL_INFO,
                sprintf("User '%s' created with ID %d.", $user->getUsername(), (int)$user->getId())
            );
            $io->success(
                sprintf(
                    "User '%s' created with ID %d.",
                    $user->getUsername(),
                    (int)$user->getId()
                )
            );

            return Command::SUCCESS;
        } catch (EntityAlreadyExists $exception) {
            $this->safeCreateCliLog(ActionLogAction::USER_CREATE, ActionLog::LEVEL_WARNING, $exception->getMessage());
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $this->safeCreateCliLog(ActionLogAction::USER_CREATE, ActionLog::LEVEL_ERROR, $exception->getMessage());
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
