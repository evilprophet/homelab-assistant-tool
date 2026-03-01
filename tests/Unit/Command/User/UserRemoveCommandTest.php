<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\User;

use EvilStudio\HAT\Command\User\UserRemoveCommand;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserRemoveCommandTest extends TestCase
{
    public function testExecuteRemovesUserInSimpleMode(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(true);
        $authUserService->expects($this->once())->method('removeSimpleUser')->with('admin');

        $tester = new CommandTester(new UserRemoveCommand($authModeResolver, $authUserService, $actionLogService));
        $exitCode = $tester->execute([
            'username' => 'admin',
            '--force' => true,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("User 'admin' removed.", $tester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenUsernameMissingInNonInteractiveMode(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(true);
        $authUserService->expects($this->never())->method('removeSimpleUser');

        $tester = new CommandTester(new UserRemoveCommand($authModeResolver, $authUserService, $actionLogService));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString("Argument 'username' is required.", $tester->getDisplay());
    }

    public function testExecuteSkipsWhenSimpleModeIsDisabled(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(false);
        $authModeResolver->expects($this->once())->method('getMode')->willReturn('oidc');
        $authUserService->expects($this->never())->method('removeSimpleUser');

        $tester = new CommandTester(new UserRemoveCommand($authModeResolver, $authUserService, $actionLogService));
        $exitCode = $tester->execute(['username' => 'admin', '--force' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
