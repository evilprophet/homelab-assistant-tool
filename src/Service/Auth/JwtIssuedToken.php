<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Auth;

use DateTimeImmutable;

class JwtIssuedToken
{
    public function __construct(
        protected string $token,
        protected DateTimeImmutable $expiresAt
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
