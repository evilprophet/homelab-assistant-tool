<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Auth;

use DateTimeImmutable;
use EvilStudio\HAT\Service\Auth\JwtIssuedToken;
use PHPUnit\Framework\TestCase;

class JwtIssuedTokenTest extends TestCase
{
    public function testExposesTokenAndExpiry(): void
    {
        $expiresAt = new DateTimeImmutable('2026-03-01 12:00:00');
        $issuedToken = new JwtIssuedToken('jwt-token', $expiresAt);

        $this->assertSame('jwt-token', $issuedToken->getToken());
        $this->assertSame($expiresAt, $issuedToken->getExpiresAt());
    }
}
