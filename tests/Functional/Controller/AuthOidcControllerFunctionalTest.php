<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Functional\Controller;

use EvilStudio\HAT\Repository\UserRepository;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use EvilStudio\HAT\Service\Auth\OidcClient;
use EvilStudio\HAT\Tests\Functional\Support\HttpFunctionalTestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthOidcControllerFunctionalTest extends HttpFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $authModeResolver = $this->createMock(AuthModeResolver::class);
        $authModeResolver->method('getMode')->willReturn(AuthModeResolver::MODE_OIDC);
        $authModeResolver->method('isOidcMode')->willReturn(true);
        $authModeResolver->method('isSimpleMode')->willReturn(false);

        static::getContainer()->set(AuthModeResolver::class, $authModeResolver);
    }

    public function testCallbackRejectsInvalidState(): void
    {
        $this->session->set('_hat_oidc_state', 'expected-state');

        $callbackResponse = $this->request('GET', '/auth/callback?state=wrong-state');
        $this->assertSame(Response::HTTP_FOUND, $callbackResponse->getStatusCode());
        $this->assertSame('/auth/login', $callbackResponse->headers->get('Location'));

        $loginResponse = $this->request('GET', '/auth/login');
        $this->assertSame(Response::HTTP_OK, $loginResponse->getStatusCode());
        $this->assertStringContainsString('Invalid OIDC state.', (string)$loginResponse->getContent());
    }

    public function testCallbackShowsProviderErrorMessage(): void
    {
        $this->session->set('_hat_oidc_state', 'expected-state');

        $callbackResponse = $this->request(
            'GET',
            '/auth/callback?state=expected-state&error=access_denied&error_description=Consent+required'
        );
        $this->assertSame(Response::HTTP_FOUND, $callbackResponse->getStatusCode());
        $this->assertSame('/auth/login', $callbackResponse->headers->get('Location'));

        $loginResponse = $this->request('GET', '/auth/login');
        $this->assertSame(Response::HTTP_OK, $loginResponse->getStatusCode());
        $this->assertStringContainsString(
            'OIDC login failed: access_denied: Consent required',
            (string)$loginResponse->getContent()
        );
    }

    public function testCallbackRequiresAuthorizationCode(): void
    {
        $this->session->set('_hat_oidc_state', 'expected-state');

        $callbackResponse = $this->request('GET', '/auth/callback?state=expected-state');
        $this->assertSame(Response::HTTP_FOUND, $callbackResponse->getStatusCode());
        $this->assertSame('/auth/login', $callbackResponse->headers->get('Location'));

        $loginResponse = $this->request('GET', '/auth/login');
        $this->assertSame(Response::HTTP_OK, $loginResponse->getStatusCode());
        $this->assertStringContainsString(
            'OIDC callback is missing authorization code.',
            (string)$loginResponse->getContent()
        );
    }

    public function testOidcLoginAndCallbackSuccessSetsAuthCookieAndCreatesUser(): void
    {
        $oidcClient = $this->createMock(OidcClient::class);
        $oidcClient->method('getProviderName')->willReturn('Test OIDC');
        $oidcClient->expects($this->once())
            ->method('buildAuthorizationUrl')
            ->willReturnCallback(
                static fn (string $state): string => sprintf('https://oidc.local/authorize?state=%s', $state)
            );
        $oidcClient->expects($this->once())
            ->method('exchangeCodeForAccessToken')
            ->with('sample-code')
            ->willReturn('oidc-access-token');
        $oidcClient->expects($this->once())
            ->method('fetchUserInfo')
            ->with('oidc-access-token')
            ->willReturn([
                'preferred_username' => 'oidc-admin',
                'sub' => 'oidc-sub-001',
            ]);

        static::getContainer()->set(OidcClient::class, $oidcClient);

        $loginResponse = $this->request('GET', '/auth/login?oidc=1&next=/devices');
        $this->assertSame(Response::HTTP_FOUND, $loginResponse->getStatusCode());

        $authorizationLocation = (string)$loginResponse->headers->get('Location');
        $this->assertStringContainsString('https://oidc.local/authorize?state=', $authorizationLocation);
        $state = $this->extractQueryValue($authorizationLocation, 'state');
        $this->assertNotSame('', $state);

        $callbackResponse = $this->request(
            'GET',
            sprintf('/auth/callback?state=%s&code=sample-code', urlencode($state))
        );
        $this->assertSame(Response::HTTP_FOUND, $callbackResponse->getStatusCode());
        $this->assertSame('/devices', $callbackResponse->headers->get('Location'));
        $this->assertNotNull($this->getCookieValue($this->getJwtCookieName()));

        $devicesResponse = $this->request('GET', '/devices');
        $this->assertSame(Response::HTTP_OK, $devicesResponse->getStatusCode());

        $userRepository = static::getContainer()->get(UserRepository::class);
        $createdUser = $userRepository->findByUsername('oidc-admin');
        $this->assertNotNull($createdUser);
        $this->assertSame('oidc-sub-001', $createdUser->getOidcSubject());
    }

    public function testCallbackShowsErrorWhenAccessTokenExchangeFails(): void
    {
        $oidcClient = $this->createMock(OidcClient::class);
        $oidcClient->method('getProviderName')->willReturn('Test OIDC');
        $oidcClient->expects($this->once())
            ->method('exchangeCodeForAccessToken')
            ->with('sample-code')
            ->willThrowException(new RuntimeException('OIDC token request failed.'));
        $oidcClient->expects($this->never())->method('fetchUserInfo');
        static::getContainer()->set(OidcClient::class, $oidcClient);

        $this->session->set('_hat_oidc_state', 'expected-state');

        $callbackResponse = $this->request('GET', '/auth/callback?state=expected-state&code=sample-code');
        $this->assertSame(Response::HTTP_FOUND, $callbackResponse->getStatusCode());
        $this->assertSame('/auth/login', $callbackResponse->headers->get('Location'));

        $loginResponse = $this->request('GET', '/auth/login');
        $this->assertSame(Response::HTTP_OK, $loginResponse->getStatusCode());
        $this->assertStringContainsString(
            'OIDC login failed: OIDC token request failed.',
            html_entity_decode((string)$loginResponse->getContent(), ENT_QUOTES)
        );
    }

    public function testCallbackShowsErrorWhenUserInfoFetchFails(): void
    {
        $oidcClient = $this->createMock(OidcClient::class);
        $oidcClient->method('getProviderName')->willReturn('Test OIDC');
        $oidcClient->expects($this->once())
            ->method('exchangeCodeForAccessToken')
            ->with('sample-code')
            ->willReturn('token-ok');
        $oidcClient->expects($this->once())
            ->method('fetchUserInfo')
            ->with('token-ok')
            ->willThrowException(new RuntimeException('OIDC userinfo request failed.'));
        static::getContainer()->set(OidcClient::class, $oidcClient);

        $this->session->set('_hat_oidc_state', 'expected-state');

        $callbackResponse = $this->request('GET', '/auth/callback?state=expected-state&code=sample-code');
        $this->assertSame(Response::HTTP_FOUND, $callbackResponse->getStatusCode());
        $this->assertSame('/auth/login', $callbackResponse->headers->get('Location'));

        $loginResponse = $this->request('GET', '/auth/login');
        $this->assertSame(Response::HTTP_OK, $loginResponse->getStatusCode());
        $this->assertStringContainsString(
            'OIDC login failed: OIDC userinfo request failed.',
            html_entity_decode((string)$loginResponse->getContent(), ENT_QUOTES)
        );
    }

    public function testLoginOidcShowsErrorWhenAuthorizationUrlBuildFails(): void
    {
        $oidcClient = $this->createMock(OidcClient::class);
        $oidcClient->method('getProviderName')->willReturn('Test OIDC');
        $oidcClient->expects($this->once())
            ->method('buildAuthorizationUrl')
            ->willThrowException(new RuntimeException('OIDC discovery failed.'));
        static::getContainer()->set(OidcClient::class, $oidcClient);

        $loginStartResponse = $this->request('GET', '/auth/login?oidc=1');
        $this->assertSame(Response::HTTP_FOUND, $loginStartResponse->getStatusCode());
        $this->assertSame('/auth/login', $loginStartResponse->headers->get('Location'));

        $loginResponse = $this->request('GET', '/auth/login');
        $this->assertSame(Response::HTTP_OK, $loginResponse->getStatusCode());
        $this->assertStringContainsString(
            'OIDC login failed: OIDC discovery failed.',
            html_entity_decode((string)$loginResponse->getContent(), ENT_QUOTES)
        );
    }

    protected function extractQueryValue(string $url, string $key): string
    {
        $query = (string)parse_url($url, PHP_URL_QUERY);
        if ($query === '') {
            return '';
        }

        $params = [];
        parse_str($query, $params);

        $value = $params[$key] ?? null;
        if (!is_string($value)) {
            return '';
        }

        return $value;
    }
}
