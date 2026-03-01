<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Auth;

use DateTimeImmutable;
use DateTimeZone;
use EvilStudio\HAT\Entity\User;
use JsonException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;

class JwtTokenService
{
    protected const string JWT_ALGORITHM = 'HS256';
    protected const string JWT_TYPE = 'JWT';
    protected const string PAYLOAD_KEY_USER_ID = 'uid';
    protected const string PAYLOAD_KEY_ISSUED_AT = 'iat';
    protected const string PAYLOAD_KEY_EXPIRES_AT = 'exp';

    protected string $secret;

    public function __construct(
        #[Autowire('%env(string:HAT_JWT_SECRET)%')]
        protected string $jwtSecret,
        #[Autowire('%env(string:APP_SECRET)%')]
        protected string $appSecret,
        #[Autowire('%env(int:HAT_JWT_TTL_SECONDS)%')]
        protected int $jwtTtlSeconds,
        #[Autowire('%env(string:HAT_JWT_COOKIE_NAME)%')]
        protected string $cookieName
    ) {
        $secret = trim($this->jwtSecret);
        if ($secret === '') {
            $secret = trim($this->appSecret);
        }

        if ($secret === '') {
            throw new RuntimeException('JWT secret cannot be empty.');
        }

        if ($this->jwtTtlSeconds < 60) {
            throw new RuntimeException('HAT_JWT_TTL_SECONDS must be greater than or equal to 60.');
        }

        if (trim($this->cookieName) === '') {
            throw new RuntimeException('HAT_JWT_COOKIE_NAME cannot be empty.');
        }

        $this->secret = $secret;
    }

    public function issueToken(User $user): JwtIssuedToken
    {
        $userId = $user->getId();
        if ($userId === null) {
            throw new RuntimeException('Cannot issue JWT for user without persisted ID.');
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + $this->jwtTtlSeconds;

        $header = [
            'alg' => self::JWT_ALGORITHM,
            'typ' => self::JWT_TYPE,
        ];
        $payload = [
            self::PAYLOAD_KEY_USER_ID => $userId,
            self::PAYLOAD_KEY_ISSUED_AT => $issuedAt,
            self::PAYLOAD_KEY_EXPIRES_AT => $expiresAt,
        ];

        $headerEncoded = $this->base64UrlEncode($this->encodeJson($header));
        $payloadEncoded = $this->base64UrlEncode($this->encodeJson($payload));
        $signature = $this->sign(sprintf('%s.%s', $headerEncoded, $payloadEncoded));
        $signatureEncoded = $this->base64UrlEncode($signature);

        return new JwtIssuedToken(
            sprintf('%s.%s.%s', $headerEncoded, $payloadEncoded, $signatureEncoded),
            (new DateTimeImmutable('@' . $expiresAt))->setTimezone(new DateTimeZone('UTC'))
        );
    }

    public function resolveUserIdFromToken(string $token): ?int
    {
        $payload = $this->decodePayload($token);
        if ($payload === null) {
            return null;
        }

        $userId = $payload[self::PAYLOAD_KEY_USER_ID] ?? null;
        if (!is_int($userId) || $userId < 1) {
            return null;
        }

        return $userId;
    }

    public function createAuthCookie(JwtIssuedToken $issuedToken, bool $isSecureRequest): Cookie
    {
        return Cookie::create($this->cookieName)
            ->withValue($issuedToken->getToken())
            ->withHttpOnly(true)
            ->withSecure($isSecureRequest)
            ->withSameSite(Cookie::SAMESITE_LAX)
            ->withPath('/')
            ->withExpires($issuedToken->getExpiresAt());
    }

    public function createClearedCookie(bool $isSecureRequest): Cookie
    {
        return Cookie::create($this->cookieName)
            ->withValue('')
            ->withHttpOnly(true)
            ->withSecure($isSecureRequest)
            ->withSameSite(Cookie::SAMESITE_LAX)
            ->withPath('/')
            ->withExpires(new DateTimeImmutable('@0'));
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }

    protected function decodePayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        if ($headerEncoded === '' || $payloadEncoded === '' || $signatureEncoded === '') {
            return null;
        }

        $expectedSignature = $this->sign(sprintf('%s.%s', $headerEncoded, $payloadEncoded));
        $actualSignature = $this->base64UrlDecode($signatureEncoded);
        if ($actualSignature === null || !hash_equals($expectedSignature, $actualSignature)) {
            return null;
        }

        $headerJson = $this->base64UrlDecode($headerEncoded);
        $payloadJson = $this->base64UrlDecode($payloadEncoded);
        if ($headerJson === null || $payloadJson === null) {
            return null;
        }

        $header = $this->decodeJson($headerJson);
        $payload = $this->decodeJson($payloadJson);
        if ($header === null || $payload === null) {
            return null;
        }

        if (($header['alg'] ?? null) !== self::JWT_ALGORITHM || ($header['typ'] ?? null) !== self::JWT_TYPE) {
            return null;
        }

        $expiresAt = $payload[self::PAYLOAD_KEY_EXPIRES_AT] ?? null;
        if (!is_int($expiresAt) || $expiresAt < time()) {
            return null;
        }

        return $payload;
    }

    protected function sign(string $value): string
    {
        return hash_hmac('sha256', $value, $this->secret, true);
    }

    protected function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }

    protected function decodeJson(string $json): ?array
    {
        try {
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $value): ?string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
