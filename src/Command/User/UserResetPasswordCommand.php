<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\User;

use EvilStudio\HAT\Command\Support\InteractiveInputTrait;
use EvilStudio\HAT\Command\Support\PasswordInputTrait;
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

#[AsCommand(name: 'hat:user:reset-password', description: 'Reset password for simple auth user')]
class UserResetPasswordCommand extends Command
{
    use InteractiveInputTrait;
    use PasswordInputTrait;

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
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'New password (visible in the process list, prefer --password-stdin)'
            )
            ->addOption(
                'password-stdin',
                null,
                InputOption::VALUE_NONE,
                'Read the new password from standard input'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->authModeResolver->isSimpleMode()) {
            $io->error(
                sprintf(
                    "Command 'hat:user:reset-password' is disabled for auth mode '%s'. " .
                    'Set HAT_AUTH_MODE=simple to enable it.',
                    $this->authModeResolver->getMode()
                )
            );

            return Command::FAILURE;
        }

        $username = $this->resolveRequiredArgument($input, $io, 'username', 'Username');
        if ($username === null) {
            return Command::FAILURE;
        }

        $password = $this->resolvePassword($input, $io, 'New password', 'Confirm new password');
        if ($password === false) {
            return Command::FAILURE;
        }

        try {
            $this->authUserService->resetSimpleUserPassword($username, $password);
            $this->safeCreateCliLog(
                ActionLogAction::USER_UPDATE,
                ActionLog::LEVEL_INFO,
                sprintf("Password reset for user '%s' completed.", $username)
            );
            $io->success(sprintf("Password reset for user '%s' completed.", $username));

            return Command::SUCCESS;
        } catch (EntityNotFound $exception) {
            $this->safeCreateCliLog(ActionLogAction::USER_UPDATE, ActionLog::LEVEL_WARNING, $exception->getMessage());
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } catch (Throwable $exception) {
            $this->safeCreateCliLog(ActionLogAction::USER_UPDATE, ActionLog::LEVEL_ERROR, $exception->getMessage());
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
