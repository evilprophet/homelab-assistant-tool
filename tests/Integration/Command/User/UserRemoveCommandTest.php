<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\User;

use EvilStudio\HAT\Command\User\UserRemoveCommand;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserRemoveCommandTest extends TestCase
{
    public function testExecuteRemovesUser(): void
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
    }
}
