<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Security;

use EvilStudio\HAT\Entity\User;
use EvilStudio\HAT\Repository\UserRepository;
use EvilStudio\HAT\Security\SimpleLoginFormAuthenticator;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SimpleLoginFormAuthenticatorTest extends TestCase
{
    public function testNormalizeNextPathAcceptsRelativePaths(): void
    {
        $authenticator = $this->createTestableAuthenticator();

        $this->assertSame('/devices', $authenticator->callNormalizeNextPath('/devices'));
        $this->assertSame('/', $authenticator->callNormalizeNextPath('/'));
        $this->assertSame('/a/b?x=1', $authenticator->callNormalizeNextPath('/a/b?x=1'));
    }

    public function testNormalizeNextPathRejectsOffSiteTargets(): void
    {
        $authenticator = $this->createTestableAuthenticator();

        $this->assertNull($authenticator->callNormalizeNextPath(''));
        $this->assertNull($authenticator->callNormalizeNextPath('evil.example'));
        $this->assertNull($authenticator->callNormalizeNextPath('http://evil.example'));
        $this->assertNull($authenticator->callNormalizeNextPath('//evil.example'));
    }

    public function testNormalizeNextPathRejectsBackslashSchemeRelativePaths(): void
    {
        $authenticator = $this->createTestableAuthenticator();

        $this->assertNull($authenticator->callNormalizeNextPath('/\\evil.example'));
        $this->assertNull($authenticator->callNormalizeNextPath('/\\'));
        $this->assertNull($authenticator->callNormalizeNextPath('/\\/evil.example'));
    }

    public function testUnknownUsernameStillCostsOneHashVerification(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userPasswordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $userRepository->expects($this->once())->method('findByUsername')->with('ghost')->willReturn(null);
        $userPasswordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($this->isInstanceOf(User::class), 'secret')
            ->willReturn(false);

        $authenticator = $this->createTestableAuthenticator($userRepository, $userPasswordHasher);

        $this->assertNull($authenticator->callLoadUser('ghost', 'secret'));
    }

    public function testKnownUsernameDoesNotVerifyTheDecoyHash(): void
    {
        $user = (new User())->setUsername('admin')->setPasswordHash('stored-hash');
        $userRepository = $this->createMock(UserRepository::class);
        $userPasswordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $userRepository->expects($this->once())->method('findByUsername')->with('admin')->willReturn($user);
        $userPasswordHasher->expects($this->never())->method('isPasswordValid');

        $authenticator = $this->createTestableAuthenticator($userRepository, $userPasswordHasher);

        $this->assertSame($user, $authenticator->callLoadUser('admin', 'secret'));
    }

    protected function createTestableAuthenticator(
        ?UserRepository $userRepository = null,
        ?UserPasswordHasherInterface $userPasswordHasher = null
    ): object {
        return new class (
            $this->createMock(UrlGeneratorInterface::class),
            $this->createMock(AuthModeResolver::class),
            $userRepository ?? $this->createMock(UserRepository::class),
            $userPasswordHasher ?? $this->createMock(UserPasswordHasherInterface::class)
        ) extends SimpleLoginFormAuthenticator {
            public function callNormalizeNextPath(string $candidate): ?string
            {
                return $this->normalizeNextPath($candidate);
            }

            public function callLoadUser(string $identifier, string $password): ?User
            {
                return $this->loadUser($identifier, $password);
            }
        };
    }
}
