<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Auth;

use EvilStudio\HAT\Service\Auth\OidcClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OidcClientTest extends TestCase
{
    public function testGetProviderNameReturnsFallbackForEmptyConfiguredName(): void
    {
        $client = new class (
            'https://issuer.example',
            'client-id',
            'client-secret',
            'https://app/callback',
            '   '
        ) extends OidcClient {
            protected function requestJson(
                string $url,
                string $method,
                array $headers = [],
                ?string $body = null
            ): array {
                return [];
            }
        };

        $this->assertSame('OIDC Provider', $client->getProviderName());
    }

    public function testBuildAuthorizationUrlUsesDiscoveryEndpointAndState(): void
    {
        $client = new class (
            'https://issuer.example',
            'client-id',
            'client-secret',
            'https://app/callback',
            'Provider'
        ) extends OidcClient {
            protected function requestJson(
                string $url,
                string $method,
                array $headers = [],
                ?string $body = null
            ): array {
                return [
                    'authorization_endpoint' => 'https://issuer.example/auth',
                    'token_endpoint' => 'https://issuer.example/token',
                    'userinfo_endpoint' => 'https://issuer.example/userinfo',
                ];
            }
        };

        $url = $client->buildAuthorizationUrl('state-123');

        $this->assertStringContainsString('https://issuer.example/auth?', $url);
        $this->assertStringContainsString('client_id=client-id', $url);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fapp%2Fcallback', $url);
        $this->assertStringContainsString('state=state-123', $url);
    }

    public function testExchangeCodeForAccessTokenReturnsTokenFromResponse(): void
    {
        $capturedCalls = [];
        $client = new class (
            'https://issuer.example',
            'client-id',
            'client-secret',
            'https://app/callback',
            'Provider',
            $capturedCalls
        ) extends OidcClient {
            public function __construct(
                string $issuer,
                string $clientId,
                string $clientSecret,
                string $redirectUri,
                string $providerName,
                protected array &$capturedCalls
            ) {
                parent::__construct($issuer, $clientId, $clientSecret, $redirectUri, $providerName);
            }

            protected function requestJson(
                string $url,
                string $method,
                array $headers = [],
                ?string $body = null
            ): array {
                $this->capturedCalls[] = ['url' => $url, 'method' => $method, 'body' => $body];

                if (str_contains($url, '.well-known/openid-configuration')) {
                    return [
                        'authorization_endpoint' => 'https://issuer.example/auth',
                        'token_endpoint' => 'https://issuer.example/token',
                        'userinfo_endpoint' => 'https://issuer.example/userinfo',
                    ];
                }

                return ['access_token' => 'access-token-123'];
            }
        };

        $token = $client->exchangeCodeForAccessToken('code-123');

        $this->assertSame('access-token-123', $token);
        $this->assertSame('POST', $capturedCalls[1]['method']);
        $this->assertStringContainsString('grant_type=authorization_code', (string)$capturedCalls[1]['body']);
        $this->assertStringContainsString('code=code-123', (string)$capturedCalls[1]['body']);
    }

    public function testExchangeCodeForAccessTokenRejectsMissingAccessToken(): void
    {
        $client = new class (
            'https://issuer.example',
            'client-id',
            'client-secret',
            'https://app/callback',
            'Provider'
        ) extends OidcClient {
            protected function requestJson(
                string $url,
                string $method,
                array $headers = [],
                ?string $body = null
            ): array {
                if (str_contains($url, '.well-known/openid-configuration')) {
                    return [
                        'authorization_endpoint' => 'https://issuer.example/auth',
                        'token_endpoint' => 'https://issuer.example/token',
                        'userinfo_endpoint' => 'https://issuer.example/userinfo',
                    ];
                }

                return ['token_type' => 'Bearer'];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OIDC token response is missing access_token.');

        $client->exchangeCodeForAccessToken('code-123');
    }

    public function testBuildAuthorizationUrlRejectsEmptyIssuer(): void
    {
        $client = new class (
            '   ',
            'client-id',
            'client-secret',
            'https://app/callback',
            'Provider'
        ) extends OidcClient {
            protected function requestJson(
                string $url,
                string $method,
                array $headers = [],
                ?string $body = null
            ): array {
                return [];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OIDC_ISSUER cannot be empty.');

        $client->buildAuthorizationUrl('state-1');
    }
}
