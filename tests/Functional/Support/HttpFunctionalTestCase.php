<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Functional\Support;

use EvilStudio\HAT\Service\Auth\AuthUserService;
use EvilStudio\HAT\Service\Auth\JwtTokenService;
use EvilStudio\HAT\Tests\Integration\Support\DatabaseIntegrationTestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

abstract class HttpFunctionalTestCase extends DatabaseIntegrationTestCase
{
    protected HttpKernelInterface $httpKernel;
    protected SessionInterface $session;

    /** @var array<string, string> */
    protected array $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpKernel = self::$kernel;
        $this->session = static::getContainer()->get('session.factory')->createSession();
        $this->session->start();
    }

    protected function request(string $method, string $uri, array $parameters = []): Response
    {
        if (mb_strtoupper($method) === Request::METHOD_POST) {
            $parameters = $this->withCsrfToken($uri, $parameters);
        }

        $requestCookies = $this->cookies;
        $requestCookies[$this->session->getName()] = $this->session->getId();

        $request = Request::create(
            $uri,
            $method,
            $parameters,
            $requestCookies,
            [],
            ['HTTP_HOST' => 'localhost']
        );
        $request->setSession($this->session);

        $response = $this->httpKernel->handle($request);
        $this->storeResponseCookies($response);
        $this->httpKernel->terminate($request, $response);

        return $response;
    }

    protected function createSimpleUser(string $username = 'admin', string $password = 'secret-1'): void
    {
        static::getContainer()->get(AuthUserService::class)->createSimpleUser($username, $password);
    }

    protected function loginAsSimpleUser(
        string $username = 'admin',
        string $password = 'secret-1',
        string $nextPath = '/'
    ): Response {
        return $this->request('POST', '/auth/login', [
            'username' => $username,
            'password' => $password,
            'next' => $nextPath,
        ]);
    }

    protected function getJwtCookieName(): string
    {
        return static::getContainer()->get(JwtTokenService::class)->getCookieName();
    }

    protected function getCookieValue(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    protected function storeResponseCookies(Response $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($this->isExpiredCookie($cookie)) {
                unset($this->cookies[$cookie->getName()]);
                continue;
            }

            $this->cookies[$cookie->getName()] = $cookie->getValue();
        }
    }

    protected function isExpiredCookie(Cookie $cookie): bool
    {
        $expiresAt = $cookie->getExpiresTime();
        if ($expiresAt !== 0 && $expiresAt <= time()) {
            return true;
        }

        return $cookie->getValue() === '' && $expiresAt <= time();
    }

    protected function withCsrfToken(string $uri, array $parameters): array
    {
        if (array_key_exists('_token', $parameters)) {
            return $parameters;
        }

        $tokenId = $this->resolveCsrfTokenId($uri);
        if ($tokenId === null) {
            return $parameters;
        }

        /** @var CsrfTokenManagerInterface $tokenManager */
        $tokenManager = static::getContainer()->get('security.csrf.token_manager');
        $request = Request::create('/');
        $request->setSession($this->session);
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);

        try {
            $parameters['_token'] = $tokenManager->getToken($tokenId)->getValue();
        } finally {
            $requestStack->pop();
        }

        return $parameters;
    }

    protected function resolveCsrfTokenId(string $uri): ?string
    {
        $path = (string)parse_url($uri, PHP_URL_PATH);

        return match (true) {
            $path === '/auth/login' => 'auth.login',
            $path === '/auth/logout' => 'auth.logout',
            $path === '/devices/new' => 'device.form.create',
            $path === '/ups/new' => 'ups.form.create',
            $path === '/schedules/new' => 'schedule.form.create',
            $path === '/logs/cleanup' => 'logs.cleanup',
            preg_match('#^/devices/(\d+)/edit$#', $path, $matches) === 1
                => sprintf('device.form.edit.%d', (int)$matches[1]),
            preg_match('#^/devices/(\d+)/remove$#', $path, $matches) === 1
                => sprintf('device.remove.%d', (int)$matches[1]),
            preg_match('#^/devices/(\d+)/start$#', $path, $matches) === 1
                => sprintf('device.start.%d', (int)$matches[1]),
            preg_match('#^/devices/(\d+)/stop$#', $path, $matches) === 1
                => sprintf('device.stop.%d', (int)$matches[1]),
            preg_match('#^/ups/(\d+)/edit$#', $path, $matches) === 1
                => sprintf('ups.form.edit.%d', (int)$matches[1]),
            preg_match('#^/ups/(\d+)/remove$#', $path, $matches) === 1
                => sprintf('ups.remove.%d', (int)$matches[1]),
            preg_match('#^/schedules/(\d+)/edit$#', $path, $matches) === 1
                => sprintf('schedule.form.edit.%d', (int)$matches[1]),
            preg_match('#^/schedules/(\d+)/remove$#', $path, $matches) === 1
                => sprintf('schedule.remove.%d', (int)$matches[1]),
            default => null,
        };
    }
}
