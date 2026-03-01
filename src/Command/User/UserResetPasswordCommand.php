<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Command\User;

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
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'New password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->authModeResolver->isSimpleMode()) {
            $io->warning(
                sprintf(
                    "Command 'hat:user:reset-password' is disabled for auth mode '%s'. " .
                    'Set HAT_AUTH_MODE=simple to enable it.',
                    $this->authModeResolver->getMode()
                )
            );

            return Command::SUCCESS;
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

        $password = (string)$input->getOption('password');
        if (!$input->hasParameterOption('--password')) {
            if (!$input->isInteractive()) {
                $io->error('Option --password is required in non-interactive mode.');

                return Command::FAILURE;
            }

            $password = trim((string)$io->askHidden('New password'));
            $passwordConfirm = trim((string)$io->askHidden('Confirm new password'));
            if ($password !== $passwordConfirm) {
                $io->error('Password confirmation does not match.');

                return Command::FAILURE;
            }
        }

        if (trim($password) === '') {
            $io->error('Password cannot be empty.');

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
