<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Auth;

use EvilStudio\HAT\Contract\AuthMode;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use PHPUnit\Framework\TestCase;

class AuthModeResolverTest extends TestCase
{
    public function testReportsSimpleMode(): void
    {
        $resolver = new AuthModeResolver(AuthMode::SIMPLE);

        $this->assertSame(AuthModeResolver::MODE_SIMPLE, $resolver->getMode());
        $this->assertTrue($resolver->isSimpleMode());
        $this->assertFalse($resolver->isOidcMode());
    }

    public function testReportsOidcMode(): void
    {
        $resolver = new AuthModeResolver(AuthMode::OIDC);

        $this->assertSame(AuthModeResolver::MODE_OIDC, $resolver->getMode());
        $this->assertFalse($resolver->isSimpleMode());
        $this->assertTrue($resolver->isOidcMode());
    }

    /**
     * The value is now validated by the enum env processor at container build time,
     * so an unsupported HAT_AUTH_MODE never reaches this class.
     */
    public function testEnumBacksExactlyTheDocumentedModes(): void
    {
        $this->assertSame(['simple', 'oidc'], AuthMode::values());
        $this->assertNull(AuthMode::tryFrom('disabled'));
        $this->assertNull(AuthMode::tryFrom('SIMPLE'));
    }
}
