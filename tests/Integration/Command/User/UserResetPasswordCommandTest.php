<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\User;

use EvilStudio\HAT\Command\User\UserResetPasswordCommand;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserResetPasswordCommandTest extends TestCase
{
    public function testExecuteResetsPassword(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(true);
        $authUserService->expects($this->once())
            ->method('resetSimpleUserPassword')
            ->with('admin', 'new-secret');

        $tester = new CommandTester(
            new UserResetPasswordCommand($authModeResolver, $authUserService, $actionLogService)
        );
        $exitCode = $tester->execute([
            'username' => 'admin',
            '--password' => 'new-secret',
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
