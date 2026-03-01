<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\EventSubscriber;

use EvilStudio\HAT\Entity\User;
use EvilStudio\HAT\EventSubscriber\AuthRequestSubscriber;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use EvilStudio\HAT\Service\Auth\JwtTokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AuthRequestSubscriberTest extends TestCase
{
    public function testRedirectsUnauthenticatedRequestToLoginRoute(): void
    {
        $jwtService = $this->createMock(JwtTokenService::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $kernel = $this->createMock(HttpKernelInterface::class);

        $jwtService->expects($this->once())->method('getCookieName')->willReturn('hat_auth');
        $jwtService->expects($this->never())->method('resolveUserIdFromToken');
        $authUserService->expects($this->never())->method('findUserById');
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('hat_auth_login', ['next' => '/devices?page=2'])
            ->willReturn('/auth/login?next=%2Fdevices%3Fpage%3D2');

        $request = Request::create('/devices?page=2');
        $request->attributes->set('_route', 'hat_devices_index');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber = new AuthRequestSubscriber($jwtService, $authUserService, $urlGenerator);
        $subscriber->onRequest($event);

        $this->assertNotNull($event->getResponse());
        $this->assertSame(Response::HTTP_FOUND, $event->getResponse()?->getStatusCode());
        $this->assertStringContainsString('/auth/login', (string)$event->getResponse()?->headers->get('Location'));
    }

    public function testSetsAuthenticatedUserAttributesForValidToken(): void
    {
        $jwtService = $this->createMock(JwtTokenService::class);
        $authUserService = $this->createMock(AuthUserService::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $user = (new User())->setUsername('admin');

        $jwtService->expects($this->once())->method('getCookieName')->willReturn('hat_auth');
        $jwtService->expects($this->once())->method('resolveUserIdFromToken')->with('jwt-token')->willReturn(10);
        $authUserService->expects($this->once())->method('findUserById')->with(10)->willReturn($user);
        $urlGenerator->expects($this->never())->method('generate');

        $request = Request::create('/devices');
        $request->attributes->set('_route', 'hat_devices_index');
        $request->cookies->set('hat_auth', 'jwt-token');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber = new AuthRequestSubscriber($jwtService, $authUserService, $urlGenerator);
        $subscriber->onRequest($event);

        $this->assertNull($event->getResponse());
        $this->assertSame($user, $request->attributes->get(AuthRequestSubscriber::AUTHENTICATED_USER_ATTRIBUTE));
        $this->assertSame('admin', $request->attributes->get(AuthRequestSubscriber::AUTHENTICATED_USERNAME_ATTRIBUTE));
    }
}
