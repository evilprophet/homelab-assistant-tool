<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\User;

use EvilStudio\HAT\Command\User\UserCreateCommand;
use EvilStudio\HAT\Entity\User;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use ReflectionProperty;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserCreateCommandTest extends TestCase
{
    public function testExecuteCreatesUserInSimpleMode(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $createdUser = $this->createPersistedUser(5, 'admin');

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(true);
        $authUserService->expects($this->once())
            ->method('createSimpleUser')
            ->with('admin', 'secret')
            ->willReturn($createdUser);

        $tester = new CommandTester(new UserCreateCommand($authModeResolver, $authUserService, $actionLogService));
        $tester->setInputs(['admin', 'secret', 'secret']);
        $exitCode = $tester->execute([], ['interactive' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("User 'admin' created with ID 5.", $tester->getDisplay());
    }

    public function testExecuteReturnsSuccessWithWarningWhenSimpleModeIsDisabled(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(false);
        $authModeResolver->expects($this->once())->method('getMode')->willReturn(AuthModeResolver::MODE_OIDC);
        $authUserService->expects($this->never())->method('createSimpleUser');

        $tester = new CommandTester(new UserCreateCommand($authModeResolver, $authUserService, $actionLogService));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("disabled for auth mode 'oidc'", $tester->getDisplay());
    }

    public function testExecuteReturnsFailureWhenPasswordsDoNotMatch(): void
    {
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $authModeResolver->expects($this->once())->method('isSimpleMode')->willReturn(true);
        $authUserService->expects($this->never())->method('createSimpleUser');

        $tester = new CommandTester(new UserCreateCommand($authModeResolver, $authUserService, $actionLogService));
        $tester->setInputs(['admin', 'secret', 'different']);
        $exitCode = $tester->execute([], ['interactive' => true]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Password confirmation does not match.', $tester->getDisplay());
    }

    protected function createPersistedUser(int $id, string $username): User
    {
        $user = (new User())
            ->setUsername($username)
            ->setPasswordHash('hash');

        $idProperty = new ReflectionProperty(User::class, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, $id);

        return $user;
    }
}
