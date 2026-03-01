<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\User;

use EvilStudio\HAT\Command\User\UserResetPasswordCommand;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserResetPasswordCommandTest extends TestCase
{
    public function testExecuteResetsPasswordInSimpleMode(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(true);
        $authUserService->expects($this->once())
            ->method('resetSimpleUserPassword')
            ->with('admin', 'secret-123');

        $tester = new CommandTester(
            new UserResetPasswordCommand($authModeResolver, $authUserService, $actionLogService)
        );
        $exitCode = $tester->execute([
            'username' => 'admin',
            '--password' => 'secret-123',
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Password reset for user 'admin' completed.", $tester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenPasswordMissingInNonInteractiveMode(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(true);
        $authUserService->expects($this->never())->method('resetSimpleUserPassword');

        $tester = new CommandTester(
            new UserResetPasswordCommand($authModeResolver, $authUserService, $actionLogService)
        );
        $exitCode = $tester->execute(['username' => 'admin'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'Option --password is required in non-interactive mode.',
            $tester->getDisplay()
        );
    }

    public function testExecuteSkipsWhenSimpleModeIsDisabled(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(false);
        $authModeResolver->expects($this->once())->method('getMode')->willReturn('oidc');
        $authUserService->expects($this->never())->method('resetSimpleUserPassword');

        $tester = new CommandTester(
            new UserResetPasswordCommand($authModeResolver, $authUserService, $actionLogService)
        );
        $exitCode = $tester->execute(['username' => 'admin', '--password' => 'secret-123'], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
