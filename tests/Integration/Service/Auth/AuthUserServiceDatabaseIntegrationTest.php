<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Service\Auth;

use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\UserRepository;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use EvilStudio\HAT\Tests\Integration\Support\DatabaseIntegrationTestCase;

class AuthUserServiceDatabaseIntegrationTest extends DatabaseIntegrationTestCase
{
    protected AuthUserService $authUserService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authUserService = new AuthUserService($this->entityManager, new UserRepository($this->entityManager));
    }

    public function testCreateResetAuthenticateAndRemoveSimpleUser(): void
    {
        $createdUser = $this->authUserService->createSimpleUser('admin', 'secret-1');
        $this->assertSame('admin', $createdUser->getUsername());
        $this->assertNotNull($createdUser->getPasswordHash());

        $authenticatedBeforeReset = $this->authUserService->authenticateSimple('admin', 'secret-1');
        $this->assertNotNull($authenticatedBeforeReset);

        $this->authUserService->resetSimpleUserPassword('admin', 'secret-2');
        $this->assertNull($this->authUserService->authenticateSimple('admin', 'secret-1'));
        $this->assertNotNull($this->authUserService->authenticateSimple('admin', 'secret-2'));

        $this->authUserService->removeSimpleUser('admin');

        $this->expectException(EntityNotFound::class);
        $this->authUserService->removeSimpleUser('admin');
    }
}
