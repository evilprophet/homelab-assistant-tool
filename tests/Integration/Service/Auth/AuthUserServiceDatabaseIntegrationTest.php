<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Service\Auth;

use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\UserRepository;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use EvilStudio\HAT\Tests\Integration\Support\DatabaseIntegrationTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthUserServiceDatabaseIntegrationTest extends DatabaseIntegrationTestCase
{
    protected AuthUserService $authUserService;

    protected UserPasswordHasherInterface $userPasswordHasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userPasswordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->authUserService = new AuthUserService(
            $this->entityManager,
            new UserRepository($this->entityManager),
            $this->userPasswordHasher
        );
    }

    public function testCreateResetAuthenticateAndRemoveSimpleUser(): void
    {
        $createdUser = $this->authUserService->createSimpleUser('admin', 'secret-1');
        $this->assertSame('admin', $createdUser->getUsername());
        $this->assertNotNull($createdUser->getPasswordHash());
        $this->assertTrue($this->userPasswordHasher->isPasswordValid($createdUser, 'secret-1'));

        // Asserted through the hasher the login path itself uses, so no second
        // authentication routine has to exist just to be testable.
        $updatedUser = $this->authUserService->resetSimpleUserPassword('admin', 'secret-2');
        $this->assertFalse($this->userPasswordHasher->isPasswordValid($updatedUser, 'secret-1'));
        $this->assertTrue($this->userPasswordHasher->isPasswordValid($updatedUser, 'secret-2'));

        $this->authUserService->removeSimpleUser('admin');

        $this->expectException(EntityNotFound::class);
        $this->authUserService->removeSimpleUser('admin');
    }
}
