<?php

declare(strict_types=1);

namespace EvilStudio\HAT\EventSubscriber;

use EvilStudio\HAT\Entity\User;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use EvilStudio\HAT\Service\Auth\JwtTokenService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class AuthRequestSubscriber implements EventSubscriberInterface
{
    public const string AUTHENTICATED_USER_ATTRIBUTE = '_hat_authenticated_user';
    public const string AUTHENTICATED_USERNAME_ATTRIBUTE = '_hat_auth_username';

    protected const array PUBLIC_ROUTE_NAMES = [
        'hat_auth_login',
        'hat_auth_callback',
        'hat_auth_logout',
        '_errors',
    ];

    protected const array PUBLIC_PATH_PREFIXES = [
        '/assets/',
        '/_error/',
        '/_profiler/',
        '/_wdt/',
    ];

    protected const array PUBLIC_EXACT_PATHS = [
        '/favicon.ico',
    ];

    public function __construct(
        protected JwtTokenService $jwtTokenService,
        protected AuthUserService $authUserService,
        protected UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 8],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($this->isPublicPath($request->getPathInfo())) {
            return;
        }

        $authenticatedUser = $this->resolveAuthenticatedUser($request);
        if ($authenticatedUser !== null) {
            $request->attributes->set(self::AUTHENTICATED_USER_ATTRIBUTE, $authenticatedUser);
            $request->attributes->set(self::AUTHENTICATED_USERNAME_ATTRIBUTE, $authenticatedUser->getUsername());
        }

        $routeName = (string)$request->attributes->get('_route', '');
        if ($routeName === '') {
            return;
        }

        if (in_array($routeName, self::PUBLIC_ROUTE_NAMES, true)) {
            return;
        }

        if ($authenticatedUser !== null) {
            return;
        }

        $nextPath = $this->normalizeNextPath($request->getRequestUri());
        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('hat_auth_login', ['next' => $nextPath])
        ));
    }

    protected function resolveAuthenticatedUser(Request $request): ?User
    {
        $token = (string)$request->cookies->get($this->jwtTokenService->getCookieName(), '');
        if ($token === '') {
            return null;
        }

        $userId = $this->jwtTokenService->resolveUserIdFromToken($token);
        if ($userId === null) {
            return null;
        }

        return $this->authUserService->findUserById($userId);
    }

    protected function isPublicPath(string $path): bool
    {
        if (in_array($path, self::PUBLIC_EXACT_PATHS, true)) {
            return true;
        }

        foreach (self::PUBLIC_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeNextPath(string $candidate): string
    {
        if ($candidate === '' || !str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return '/';
        }

        return $candidate;
    }
}
