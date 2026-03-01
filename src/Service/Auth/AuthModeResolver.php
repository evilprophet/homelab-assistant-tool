<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Auth;

use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AuthModeResolver
{
    public const string MODE_SIMPLE = 'simple';
    public const string MODE_OIDC = 'oidc';

    protected const array ALLOWED_MODES = [
        self::MODE_SIMPLE,
        self::MODE_OIDC,
    ];

    public function __construct(
        #[Autowire('%env(string:HAT_AUTH_MODE)%')]
        protected string $authMode,
    ) {
    }

    public function getMode(): string
    {
        $mode = mb_strtolower(trim($this->authMode));
        if (in_array($mode, self::ALLOWED_MODES, true)) {
            return $mode;
        }

        throw new LogicException(
            sprintf(
                "Invalid HAT_AUTH_MODE '%s'. Allowed values: %s.",
                $this->authMode,
                implode(', ', self::ALLOWED_MODES)
            )
        );
    }

    public function isSimpleMode(): bool
    {
        return $this->getMode() === self::MODE_SIMPLE;
    }

    public function isOidcMode(): bool
    {
        return $this->getMode() === self::MODE_OIDC;
    }
}
