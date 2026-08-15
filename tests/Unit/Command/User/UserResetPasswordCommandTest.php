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
        $actionLogService = $this->createStub(ActionLogService::class);

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

    public function testExecuteReadsPasswordFromStandardInput(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createStub(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(true);
        $authUserService->expects($this->once())
            ->method('resetSimpleUserPassword')
            ->with('admin', 'secret-123');

        $command = new class ($authModeResolver, $authUserService, $actionLogService) extends UserResetPasswordCommand {
            protected function readStandardInput(): string|false
            {
                return "secret-123\n";
            }
        };

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'username' => 'admin',
            '--password-stdin' => true,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testExecuteReturnsFailureWhenPasswordMissingInNonInteractiveMode(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createStub(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(true);
        $authUserService->expects($this->never())->method('resetSimpleUserPassword');

        $tester = new CommandTester(
            new UserResetPasswordCommand($authModeResolver, $authUserService, $actionLogService)
        );
        $exitCode = $tester->execute(['username' => 'admin'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString(
            'Option --password-stdin or --password is required',
            $tester->getDisplay()
        );
    }

    public function testExecuteFailsWhenSimpleModeIsDisabled(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createStub(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(false);
        $authModeResolver->expects($this->once())->method('getMode')->willReturn('oidc');
        $authUserService->expects($this->never())->method('resetSimpleUserPassword');

        $tester = new CommandTester(
            new UserResetPasswordCommand($authModeResolver, $authUserService, $actionLogService)
        );
        $exitCode = $tester->execute(['username' => 'admin', '--password' => 'secret-123'], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
    }
}
