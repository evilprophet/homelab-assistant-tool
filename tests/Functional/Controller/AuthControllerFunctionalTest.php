<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Functional\Controller;

use EvilStudio\HAT\Tests\Functional\Support\HttpFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerFunctionalTest extends HttpFunctionalTestCase
{
    public function testProtectedRouteRedirectsGuestToLogin(): void
    {
        $response = $this->request('GET', '/devices');

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());

        $location = (string)$response->headers->get('Location');
        $this->assertStringContainsString('/auth/login', $location);
        $this->assertStringContainsString('next=/devices', $location);
    }

    public function testLoginReturnsUnauthorizedForInvalidCredentials(): void
    {
        $this->createSimpleUser('admin', 'secret-1');

        $response = $this->request('POST', '/auth/login', [
            'username' => 'admin',
            'password' => 'invalid-password',
            'next' => '/',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertStringContainsString('Invalid credentials.', (string)$response->getContent());
        $this->assertNull($this->getCookieValue($this->getJwtCookieName()));
    }

    public function testLoginAndLogoutManageAuthCookie(): void
    {
        $this->createSimpleUser('admin', 'secret-1');

        $loginResponse = $this->request('POST', '/auth/login', [
            'username' => 'admin',
            'password' => 'secret-1',
            'next' => '/devices',
        ]);

        $this->assertSame(Response::HTTP_FOUND, $loginResponse->getStatusCode());
        $this->assertSame('/devices', $loginResponse->headers->get('Location'));
        $this->assertNotNull($this->getCookieValue($this->getJwtCookieName()));

        $devicesResponse = $this->request('GET', '/devices');
        $this->assertSame(Response::HTTP_OK, $devicesResponse->getStatusCode());

        $logoutResponse = $this->request('POST', '/auth/logout');
        $this->assertSame(Response::HTTP_FOUND, $logoutResponse->getStatusCode());
        $this->assertSame('/auth/login', $logoutResponse->headers->get('Location'));
        $this->assertNull($this->getCookieValue($this->getJwtCookieName()));
    }
}
