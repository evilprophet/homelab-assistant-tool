<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Auth;

use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use LogicException;
use PHPUnit\Framework\TestCase;

class AuthModeResolverTest extends TestCase
{
    public function testGetModeNormalizesSimpleMode(): void
    {
        $resolver = new AuthModeResolver('  SIMPLE  ');

        $this->assertSame(AuthModeResolver::MODE_SIMPLE, $resolver->getMode());
        $this->assertTrue($resolver->isSimpleMode());
        $this->assertFalse($resolver->isOidcMode());
    }

    public function testGetModeNormalizesOidcMode(): void
    {
        $resolver = new AuthModeResolver('OidC');

        $this->assertSame(AuthModeResolver::MODE_OIDC, $resolver->getMode());
        $this->assertFalse($resolver->isSimpleMode());
        $this->assertTrue($resolver->isOidcMode());
    }

    public function testGetModeThrowsForUnsupportedValue(): void
    {
        $resolver = new AuthModeResolver('disabled');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Invalid HAT_AUTH_MODE 'disabled'");

        $resolver->getMode();
    }
}
