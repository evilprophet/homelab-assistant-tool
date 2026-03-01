<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Auth;

use EvilStudio\HAT\Entity\User;
use EvilStudio\HAT\Service\Auth\JwtTokenService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;

class JwtTokenServiceTest extends TestCase
{
    public function testIssueTokenAndResolveUserId(): void
    {
        $service = new JwtTokenService('unit-test-secret', '', 3600, 'hat_auth');
        $user = $this->createPersistedUser(42);

        $issuedToken = $service->issueToken($user);

        $this->assertSame(42, $service->resolveUserIdFromToken($issuedToken->getToken()));
        $this->assertNotSame('', $issuedToken->getToken());
    }

    public function testResolveUserIdReturnsNullForTamperedToken(): void
    {
        $service = new JwtTokenService('unit-test-secret', '', 3600, 'hat_auth');
        $user = $this->createPersistedUser(7);
        $issuedToken = $service->issueToken($user)->getToken();

        $tokenParts = explode('.', $issuedToken);
        $this->assertCount(3, $tokenParts);

        $originalSignature = $tokenParts[2];
        $tamperedSignature = ($originalSignature[0] ?? 'a') === 'a'
            ? 'b' . substr($originalSignature, 1)
            : 'a' . substr($originalSignature, 1);
        $tokenParts[2] = $tamperedSignature;
        $tamperedToken = implode('.', $tokenParts);

        $this->assertNull($service->resolveUserIdFromToken($tamperedToken));
    }

    public function testIssueTokenThrowsWhenUserHasNoId(): void
    {
        $service = new JwtTokenService('unit-test-secret', '', 3600, 'hat_auth');
        $user = (new User())->setUsername('no-id-user');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot issue JWT for user without persisted ID.');

        $service->issueToken($user);
    }

    public function testConstructorThrowsWhenTtlIsTooLow(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HAT_JWT_TTL_SECONDS must be greater than or equal to 60.');

        new JwtTokenService('unit-test-secret', '', 59, 'hat_auth');
    }

    public function testConstructorFallsBackToAppSecretWhenJwtSecretIsBlank(): void
    {
        $service = new JwtTokenService('', 'app-secret', 3600, 'hat_auth');
        $user = $this->createPersistedUser(3);

        $issuedToken = $service->issueToken($user);

        $this->assertSame(3, $service->resolveUserIdFromToken($issuedToken->getToken()));
    }

    public function testCreateAuthCookieBuildsHttpOnlyCookieWithExpiry(): void
    {
        $service = new JwtTokenService('unit-test-secret', '', 3600, 'hat_auth');
        $issuedToken = $service->issueToken($this->createPersistedUser(9));

        $cookie = $service->createAuthCookie($issuedToken, true);

        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertSame('hat_auth', $cookie->getName());
        $this->assertSame($issuedToken->getToken(), $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
    }

    public function testCreateClearedCookieExpiresAtUnixEpoch(): void
    {
        $service = new JwtTokenService('unit-test-secret', '', 3600, 'hat_auth');
        $cookie = $service->createClearedCookie(false);

        $this->assertSame('hat_auth', $cookie->getName());
        $this->assertSame('', $cookie->getValue());
        $this->assertFalse($cookie->isSecure());
        $this->assertSame(0, $cookie->getExpiresTime());
    }

    public function testResolveUserIdReturnsNullForExpiredToken(): void
    {
        $service = new JwtTokenService('unit-test-secret', '', 3600, 'hat_auth');
        $expiredToken = $this->createSignedToken(11, time() - 10, 'unit-test-secret');

        $this->assertNull($service->resolveUserIdFromToken($expiredToken));
    }

    public function testResolveUserIdReturnsNullWhenPayloadUserIdIsInvalid(): void
    {
        $service = new JwtTokenService('unit-test-secret', '', 3600, 'hat_auth');
        $invalidUidToken = $this->createSignedToken(0, time() + 3600, 'unit-test-secret');

        $this->assertNull($service->resolveUserIdFromToken($invalidUidToken));
    }

    protected function createPersistedUser(int $id): User
    {
        $user = (new User())
            ->setUsername('user-' . $id)
            ->setPasswordHash('hash');

        $idProperty = new ReflectionProperty(User::class, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, $id);

        return $user;
    }

    protected function createSignedToken(int $userId, int $expiresAt, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = ['uid' => $userId, 'iat' => time() - 60, 'exp' => $expiresAt];

        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $headerEncoded = $encode((string)json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = $encode((string)json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $secret, true);
        $signatureEncoded = $encode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
}
