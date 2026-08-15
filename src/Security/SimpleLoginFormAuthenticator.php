<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Security;

use EvilStudio\HAT\Entity\User;
use EvilStudio\HAT\Repository\UserRepository;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class SimpleLoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;
    use NextPathTrait;

    public const string LOGIN_ROUTE = 'hat_auth_login';
    public const string NEXT_QUERY_KEY = 'next';
    public const string LOGIN_CSRF_TOKEN_ID = 'auth.login';

    // Verified instead of a real hash when the username does not exist, so both
    // outcomes cost one argon2id verification and the response time stops
    // revealing which usernames are registered.
    protected const string TIMING_DECOY_HASH =
        '$argon2id$v=19$m=65536,t=2,p=1$Oh6m5Rw65HtN5MTrk9hnPQ$HRKX1GMCxI3+L8l0j0MKPFAm4H/ddNvSPM7rU4ODAVc';

    public function __construct(
        protected UrlGeneratorInterface $urlGenerator,
        protected AuthModeResolver $authModeResolver,
        protected UserRepository $userRepository,
        protected UserPasswordHasherInterface $userPasswordHasher
    ) {
    }

    public function supports(Request $request): bool
    {
        return $this->authModeResolver->isSimpleMode()
            && $request->isMethod(Request::METHOD_POST)
            && $request->attributes->get('_route') === self::LOGIN_ROUTE;
    }

    public function authenticate(Request $request): Passport
    {
        $username = trim((string)$request->request->get('username', ''));
        $password = (string)$request->request->get('password', '');

        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);
        }

        if ($username === '' || $password === '') {
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        return new Passport(
            new UserBadge($username, fn (string $identifier): ?User => $this->loadUser($identifier, $password)),
            new PasswordCredentials($password),
            [new CsrfTokenBadge(self::LOGIN_CSRF_TOKEN_ID, (string)$request->request->get('_token', ''))]
        );
    }

    protected function loadUser(string $identifier, string $password): ?User
    {
        $user = $this->userRepository->findByUsername($identifier);
        if ($user !== null) {
            return $user;
        }

        $this->userPasswordHasher->isPasswordValid($this->createTimingDecoyUser(), $password);

        return null;
    }

    protected function createTimingDecoyUser(): User
    {
        return (new User())
            ->setUsername('')
            ->setPasswordHash(self::TIMING_DECOY_HASH);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $nextPath = $this->normalizeNextPath((string)$request->request->get(self::NEXT_QUERY_KEY, ''));

        if ($nextPath === null && $request->hasSession()) {
            $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
            $nextPath = $this->normalizeTargetPath($request, (string)$targetPath);
            if ($nextPath !== null) {
                $this->removeTargetPath($request->getSession(), $firewallName);
            }
        }

        return new RedirectResponse($nextPath ?? $this->urlGenerator->generate('hat_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        $nextPath = $this->normalizeNextPath((string)$request->request->get(self::NEXT_QUERY_KEY, ''));
        if ($nextPath === null) {
            return new RedirectResponse($this->getLoginUrl($request));
        }

        return new RedirectResponse(
            $this->urlGenerator->generate(self::LOGIN_ROUTE, [self::NEXT_QUERY_KEY => $nextPath])
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $nextPath = $this->normalizeNextPath($request->getRequestUri());

        if ($nextPath === null) {
            return new RedirectResponse($this->getLoginUrl($request));
        }

        return new RedirectResponse(
            $this->urlGenerator->generate(self::LOGIN_ROUTE, [self::NEXT_QUERY_KEY => $nextPath])
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
