<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Auth;

use EvilStudio\HAT\Contract\AuthMode;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AuthModeResolver
{
    public const string MODE_SIMPLE = AuthMode::SIMPLE->value;
    public const string MODE_OIDC = AuthMode::OIDC->value;

    public function __construct(
        // The enum processor resolves at container build time, so a typo fails once
        // with a clear message instead of turning every request into a 500.
        #[Autowire('%env(enum:' . AuthMode::class . ':trim:HAT_AUTH_MODE)%')]
        protected AuthMode $authMode,
    ) {
    }

    public function getMode(): string
    {
        return $this->authMode->value;
    }

    public function isSimpleMode(): bool
    {
        return $this->authMode === AuthMode::SIMPLE;
    }

    public function isOidcMode(): bool
    {
        return $this->authMode === AuthMode::OIDC;
    }
}
