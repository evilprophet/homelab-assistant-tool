<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Auth;

use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\User;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\UserRepository;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AuthUserServiceTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testCreateSimpleUserRejectsEmptyUsername(): void
    {
        $service = new AuthUserService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserRepository::class)
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username cannot be empty.');

        $service->createSimpleUser('   ', 'secret');
    }

    public function testCreateSimpleUserRejectsDuplicateUsername(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(UserRepository::class);
        $existingUser = (new User())->setUsername('admin');
        $this->setEntityId($existingUser, 1);

        $repository->expects($this->once())->method('findByUsername')->with('admin')->willReturn($existingUser);
        $entityManager->expects($this->never())->method('persist');

        $service = new AuthUserService($entityManager, $repository);

        $this->expectException(EntityAlreadyExists::class);
        $this->expectExceptionMessage("User with username 'admin' already exists.");

        $service->createSimpleUser('admin', 'secret');
    }

    public function testCreateSimpleUserPersistsUserWithHashedPassword(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(UserRepository::class);

        $repository->expects($this->once())->method('findByUsername')->with('admin')->willReturn(null);
        $entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(User::class));
        $entityManager->expects($this->once())->method('flush');

        $service = new AuthUserService($entityManager, $repository);
        $user = $service->createSimpleUser(' admin ', 'secret');

        $this->assertSame('admin', $user->getUsername());
        $this->assertNotNull($user->getPasswordHash());
        $this->assertTrue(password_verify('secret', (string)$user->getPasswordHash()));
        $this->assertNull($user->getOidcSubject());
    }

    public function testAuthenticateSimpleReturnsUserForValidCredentials(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(UserRepository::class);
        $user = (new User())
            ->setUsername('admin')
            ->setPasswordHash(password_hash('secret', PASSWORD_BCRYPT));

        $repository->expects($this->once())->method('findByUsername')->with('admin')->willReturn($user);

        $service = new AuthUserService($entityManager, $repository);

        $this->assertSame($user, $service->authenticateSimple('admin', 'secret'));
    }

    public function testCreateOrUpdateFromOidcUpdatesExistingSubjectUserUsername(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(UserRepository::class);
        $subjectUser = (new User())
            ->setUsername('old-name')
            ->setOidcSubject('oidc-1');
        $this->setEntityId($subjectUser, 10);

        $repository->expects($this->once())->method('findByOidcSubject')->with('oidc-1')->willReturn($subjectUser);
        $repository->expects($this->once())->method('findByUsername')->with('new-name')->willReturn(null);
        $entityManager->expects($this->once())->method('persist')->with($subjectUser);
        $entityManager->expects($this->once())->method('flush');

        $service = new AuthUserService($entityManager, $repository);
        $result = $service->createOrUpdateFromOidc('oidc-1', 'new-name');

        $this->assertSame($subjectUser, $result);
        $this->assertSame('new-name', $subjectUser->getUsername());
    }

    public function testCreateOrUpdateFromOidcLinksExistingUsernameWithSubject(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(UserRepository::class);
        $usernameUser = (new User())
            ->setUsername('admin')
            ->setOidcSubject(null);
        $this->setEntityId($usernameUser, 20);

        $repository->expects($this->once())->method('findByOidcSubject')->with('oidc-1')->willReturn(null);
        $repository->expects($this->once())->method('findByUsername')->with('admin')->willReturn($usernameUser);
        $entityManager->expects($this->once())->method('persist')->with($usernameUser);
        $entityManager->expects($this->once())->method('flush');

        $service = new AuthUserService($entityManager, $repository);
        $result = $service->createOrUpdateFromOidc('oidc-1', 'admin');

        $this->assertSame($usernameUser, $result);
        $this->assertSame('oidc-1', $usernameUser->getOidcSubject());
    }

    public function testCreateOrUpdateFromOidcRejectsUsernameLinkedToDifferentSubject(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(UserRepository::class);
        $usernameUser = (new User())
            ->setUsername('admin')
            ->setOidcSubject('other-subject');

        $repository->expects($this->once())->method('findByOidcSubject')->with('oidc-1')->willReturn(null);
        $repository->expects($this->once())->method('findByUsername')->with('admin')->willReturn($usernameUser);
        $entityManager->expects($this->never())->method('persist');

        $service = new AuthUserService($entityManager, $repository);

        $this->expectException(EntityAlreadyExists::class);
        $this->expectExceptionMessage("Username 'admin' is already linked to a different OIDC subject.");

        $service->createOrUpdateFromOidc('oidc-1', 'admin');
    }

    public function testRemoveSimpleUserRemovesExistingUser(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(UserRepository::class);
        $user = (new User())->setUsername('admin');

        $repository->expects($this->once())->method('findByUsername')->with('admin')->willReturn($user);
        $entityManager->expects($this->once())->method('remove')->with($user);
        $entityManager->expects($this->once())->method('flush');

        $service = new AuthUserService($entityManager, $repository);
        $service->removeSimpleUser('admin');

        $this->addToAssertionCount(1);
    }

    public function testRemoveSimpleUserThrowsWhenUserNotFound(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(UserRepository::class);

        $repository->expects($this->once())->method('findByUsername')->with('admin')->willReturn(null);
        $entityManager->expects($this->never())->method('remove');

        $service = new AuthUserService($entityManager, $repository);

        $this->expectException(EntityNotFound::class);
        $this->expectExceptionMessage("User with username 'admin' not found.");

        $service->removeSimpleUser('admin');
    }

    public function testResetSimpleUserPasswordUpdatesPasswordHash(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(UserRepository::class);
        $user = (new User())->setUsername('admin')->setPasswordHash('legacy-hash');

        $repository->expects($this->once())->method('findByUsername')->with('admin')->willReturn($user);
        $entityManager->expects($this->once())->method('persist')->with($user);
        $entityManager->expects($this->once())->method('flush');

        $service = new AuthUserService($entityManager, $repository);
        $updatedUser = $service->resetSimpleUserPassword('admin', 'new-secret');

        $this->assertSame($user, $updatedUser);
        $this->assertTrue(password_verify('new-secret', (string)$updatedUser->getPasswordHash()));
    }
}
