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
                    'issuer' => 'https://issuer.example',
                    'authorization_endpoint' => 'https://issuer.example/auth',
                    'token_endpoint' => 'https://issuer.example/token',
                    'userinfo_endpoint' => 'https://issuer.example/userinfo',
                ];
            }
        };

        $url = $client->buildAuthorizationUrl('state-123', 'verifier-abc');

        $this->assertStringContainsString('https://issuer.example/auth?', $url);
        $this->assertStringContainsString('client_id=client-id', $url);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fapp%2Fcallback', $url);
        $this->assertStringContainsString('state=state-123', $url);
    }

    public function testBuildAuthorizationUrlMergesQueryIntoEndpointThatAlreadyHasOne(): void
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
                    'issuer' => 'https://issuer.example',
                    'authorization_endpoint' => 'https://issuer.example/auth?realm=lab',
                    'token_endpoint' => 'https://issuer.example/token',
                    'userinfo_endpoint' => 'https://issuer.example/userinfo',
                ];
            }
        };

        $url = $client->buildAuthorizationUrl('state-123', 'verifier-abc');

        $this->assertStringContainsString('https://issuer.example/auth?realm=lab&client_id=client-id', $url);
        $this->assertSame(1, substr_count($url, '?'));
    }

    public function testAuthorizationUrlCarriesAnS256PkceChallenge(): void
    {
        $client = $this->createDiscoveryClient();
        $verifier = $client->createCodeVerifier();

        $url = $client->buildAuthorizationUrl('state-123', $verifier);
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame($expected, $query['code_challenge']);
        // The challenge is a hash, so the verifier itself must never travel in the URL.
        $this->assertStringNotContainsString($verifier, $url);
    }

    public function testCodeVerifierMeetsRfc7636LengthAndCharset(): void
    {
        $client = $this->createDiscoveryClient();

        for ($i = 0; $i < 5; $i++) {
            $verifier = $client->createCodeVerifier();
            $this->assertGreaterThanOrEqual(43, strlen($verifier));
            $this->assertLessThanOrEqual(128, strlen($verifier));
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-._~]+$/', $verifier);
        }
    }

    public function testTokenRequestSendsTheCodeVerifier(): void
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
                $this->capturedCalls[] = ['url' => $url, 'body' => $body];

                if (str_contains($url, '.well-known/openid-configuration')) {
                    return [
                        'issuer' => 'https://issuer.example',
                        'token_endpoint' => 'https://issuer.example/token',
                    ];
                }

                return ['access_token' => 'token'];
            }
        };

        $client->exchangeCodeForAccessToken('code-123', 'verifier-abc');

        $this->assertStringContainsString('code_verifier=verifier-abc', (string)$capturedCalls[1]['body']);
    }

    public function testDiscoveryDocumentClaimingADifferentIssuerIsRejected(): void
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
                // Served from the configured host, but claiming to be someone else.
                return [
                    'issuer' => 'https://evil.example',
                    'authorization_endpoint' => 'https://evil.example/auth',
                ];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not match the configured OIDC_ISSUER');

        $client->buildAuthorizationUrl('state-1', 'verifier-abc');
    }

    /**
     * Everything below drives the real requestJson() through a stubbed transport, so
     * the response handling is executed rather than mocked away.
     */
    public function testFetchUserInfoSendsBearerTokenAndDecodesTheResponse(): void
    {
        $client = $this->createTransportClient([
            'https://issuer.example/.well-known/openid-configuration' => [
                json_encode(['issuer' => 'https://issuer.example', 'userinfo_endpoint' => 'https://issuer.example/me']),
                ['HTTP/1.1 200 OK'],
            ],
            'https://issuer.example/me' => [
                json_encode(['sub' => 'user-1', 'preferred_username' => 'admin']),
                ['HTTP/1.1 200 OK'],
            ],
        ]);

        $userInfo = $client->fetchUserInfo('token-abc');

        $this->assertSame('user-1', $userInfo['sub']);
        $this->assertContains('Authorization: Bearer token-abc', $client->capturedHeaders);
    }

    public function testTransportFailureIsReportedAsAFailedRequest(): void
    {
        $client = $this->createTransportClient([
            'https://issuer.example/.well-known/openid-configuration' => [false, []],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OIDC request failed for URL');

        $client->fetchUserInfo('token-abc');
    }

    public function testNonSuccessStatusIsRejected(): void
    {
        $client = $this->createTransportClient([
            'https://issuer.example/.well-known/openid-configuration' => ['{}', ['HTTP/1.1 503 Service Unavailable']],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP status 503');

        $client->fetchUserInfo('token-abc');
    }

    public function testMissingStatusLineIsTreatedAsFailure(): void
    {
        $client = $this->createTransportClient([
            'https://issuer.example/.well-known/openid-configuration' => ['{}', ['Content-Type: application/json']],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP status 0');

        $client->fetchUserInfo('token-abc');
    }

    public function testInvalidJsonIsRejected(): void
    {
        $client = $this->createTransportClient([
            'https://issuer.example/.well-known/openid-configuration' => ['not json at all', ['HTTP/1.1 200 OK']],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not valid JSON');

        $client->fetchUserInfo('token-abc');
    }

    public function testScalarJsonIsRejected(): void
    {
        $client = $this->createTransportClient([
            'https://issuer.example/.well-known/openid-configuration' => ['"a string"', ['HTTP/1.1 200 OK']],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JSON must be an object');

        $client->fetchUserInfo('token-abc');
    }

    /**
     * @param array<string, array{0: string|false, 1: array<int, string>}> $responses
     */
    protected function createTransportClient(array $responses): OidcClient
    {
        return new class (
            'https://issuer.example',
            'client-id',
            'client-secret',
            'https://app/callback',
            'Provider',
            $responses
        ) extends OidcClient {
            public array $capturedHeaders = [];

            public function __construct(
                string $issuer,
                string $clientId,
                string $clientSecret,
                string $redirectUri,
                string $providerName,
                protected array $responses
            ) {
                parent::__construct($issuer, $clientId, $clientSecret, $redirectUri, $providerName);
            }

            protected function sendRequest(string $url, string $method, array $headers, ?string $body): array
            {
                $this->capturedHeaders = array_merge($this->capturedHeaders, $headers);

                return $this->responses[$url] ?? ['{}', ['HTTP/1.1 404 Not Found']];
            }
        };
    }

    protected function createDiscoveryClient(): OidcClient
    {
        return new class (
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
                    'issuer' => 'https://issuer.example',
                    'authorization_endpoint' => 'https://issuer.example/auth',
                    'token_endpoint' => 'https://issuer.example/token',
                    'userinfo_endpoint' => 'https://issuer.example/userinfo',
                ];
            }
        };
    }

    public function testExtractStatusCodeUsesTheFinalHopOfARedirectChain(): void
    {
        $client = new class (
            'https://issuer.example',
            'id',
            'secret',
            'https://app/callback',
            'Provider'
        ) extends OidcClient {
            public function callExtractStatusCode(array $headers): int
            {
                return $this->extractStatusCode($headers);
            }
        };

        $this->assertSame(200, $client->callExtractStatusCode([
            'HTTP/1.1 302 Found',
            'Location: https://issuer.example/auth/',
            'HTTP/1.1 200 OK',
            'Content-Type: application/json',
        ]));
        $this->assertSame(200, $client->callExtractStatusCode(['HTTP/1.1 200 OK']));
        $this->assertSame(401, $client->callExtractStatusCode(['HTTP/1.1 302 Found', 'HTTP/1.1 401 Unauthorized']));
        $this->assertSame(0, $client->callExtractStatusCode(['Content-Type: application/json']));
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
                        'issuer' => 'https://issuer.example',
                        'authorization_endpoint' => 'https://issuer.example/auth',
                        'token_endpoint' => 'https://issuer.example/token',
                        'userinfo_endpoint' => 'https://issuer.example/userinfo',
                    ];
                }

                return ['access_token' => 'access-token-123'];
            }
        };

        $token = $client->exchangeCodeForAccessToken('code-123', 'verifier-abc');

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
                        'issuer' => 'https://issuer.example',
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

        $client->exchangeCodeForAccessToken('code-123', 'verifier-abc');
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

        $client->buildAuthorizationUrl('state-1', 'verifier-abc');
    }
}
