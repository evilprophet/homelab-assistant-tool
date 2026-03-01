<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Controller;

use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\EventSubscriber\AuthRequestSubscriber;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Auth\AuthModeResolver;
use EvilStudio\HAT\Service\Auth\AuthUserService;
use EvilStudio\HAT\Service\Auth\JwtTokenService;
use EvilStudio\HAT\Service\Auth\OidcClient;
use Random\RandomException;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
class AuthController extends AbstractController
{
    protected const string OIDC_QUERY_FLAG = 'oidc';
    protected const string OIDC_SESSION_STATE_KEY = '_hat_oidc_state';
    protected const string OIDC_SESSION_NEXT_KEY = '_hat_oidc_next';
    protected const string NEXT_QUERY_KEY = 'next';
    protected const string CSRF_TOKEN_LOGIN = 'auth.login';
    protected const string CSRF_TOKEN_LOGOUT = 'auth.logout';

    public function __construct(
        protected AuthModeResolver $authModeResolver,
        protected AuthUserService $authUserService,
        protected JwtTokenService $jwtTokenService,
        protected OidcClient $oidcClient,
        protected ActionLogService $actionLogService
    ) {
    }

    #[Route(path: '/login', name: 'hat_auth_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($request->attributes->has(AuthRequestSubscriber::AUTHENTICATED_USER_ATTRIBUTE)) {
            return $this->redirectToRoute('hat_dashboard');
        }

        $mode = $this->authModeResolver->getMode();
        $nextPath = $this->normalizeNextPath((string)$request->query->get(self::NEXT_QUERY_KEY, '/'));
        $errorMessage = null;

        if ($this->authModeResolver->isOidcMode()) {
            if ($request->query->getBoolean(self::OIDC_QUERY_FLAG)) {
                return $this->startOidcLogin($request, $nextPath);
            }
        } elseif ($request->isMethod(Request::METHOD_POST)) {
            $nextPath = $this->normalizeNextPath((string)$request->request->get(self::NEXT_QUERY_KEY, '/'));
            $csrfToken = (string)$request->request->get('_token', '');
            if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_LOGIN, $csrfToken)) {
                $errorMessage = 'Invalid CSRF token.';
                $this->safeCreateWebLog(
                    ActionLogAction::AUTH_LOGIN,
                    ActionLog::LEVEL_WARNING,
                    'Simple login failed: invalid CSRF token.'
                );

                return $this->render('auth/login.html.twig', [
                    'page_title' => 'Login',
                    'auth_mode' => $mode,
                    'oidc_provider_name' => $this->oidcClient->getProviderName(),
                    'next_path' => $nextPath,
                    'error_message' => $errorMessage,
                ])->setStatusCode(Response::HTTP_FORBIDDEN);
            }

            $username = trim((string)$request->request->get('username', ''));
            $password = (string)$request->request->get('password', '');

            $user = $this->authUserService->authenticateSimple($username, $password);
            if ($user !== null) {
                $issuedToken = $this->jwtTokenService->issueToken($user);

                $response = new RedirectResponse($nextPath);
                $response->headers->setCookie(
                    $this->jwtTokenService->createAuthCookie($issuedToken, $request->isSecure())
                );

                $this->safeCreateWebLog(
                    ActionLogAction::AUTH_LOGIN,
                    ActionLog::LEVEL_INFO,
                    sprintf("Simple login success for user '%s'.", $user->getUsername())
                );

                return $response;
            }

            $errorMessage = 'Invalid credentials.';
            $this->safeCreateWebLog(
                ActionLogAction::AUTH_LOGIN,
                ActionLog::LEVEL_WARNING,
                sprintf("Simple login failed for username '%s'.", $username === '' ? '<empty>' : $username)
            );
        }

        $response = $this->render('auth/login.html.twig', [
            'page_title' => 'Login',
            'auth_mode' => $mode,
            'oidc_provider_name' => $this->oidcClient->getProviderName(),
            'next_path' => $nextPath,
            'error_message' => $errorMessage,
        ]);

        if ($errorMessage !== null) {
            $response->setStatusCode(Response::HTTP_UNAUTHORIZED);
        }

        return $response;
    }

    #[Route(path: '/callback', name: 'hat_auth_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        if (!$this->authModeResolver->isOidcMode()) {
            $this->addFlash('warning', 'OIDC callback is disabled because auth mode is not set to oidc.');

            return $this->redirectToRoute('hat_auth_login');
        }

        $session = $request->getSession();
        $expectedState = (string)$session->get(self::OIDC_SESSION_STATE_KEY, '');
        $session->remove(self::OIDC_SESSION_STATE_KEY);

        $returnedState = trim((string)$request->query->get('state', ''));
        if ($expectedState === '' || $returnedState === '' || !hash_equals($expectedState, $returnedState)) {
            $this->addFlash('error', 'Invalid OIDC state.');
            $this->safeCreateWebLog(
                ActionLogAction::AUTH_CALLBACK,
                ActionLog::LEVEL_ERROR,
                'OIDC callback failed: invalid state.'
            );

            return $this->redirectToRoute('hat_auth_login');
        }

        $error = trim((string)$request->query->get('error', ''));
        if ($error !== '') {
            $errorDescription = trim((string)$request->query->get('error_description', ''));
            $message = $errorDescription === '' ? $error : sprintf('%s: %s', $error, $errorDescription);
            $this->addFlash('error', sprintf('OIDC login failed: %s', $message));
            $this->safeCreateWebLog(
                ActionLogAction::AUTH_CALLBACK,
                ActionLog::LEVEL_ERROR,
                sprintf('OIDC callback returned error: %s', $message)
            );

            return $this->redirectToRoute('hat_auth_login');
        }

        $code = trim((string)$request->query->get('code', ''));
        if ($code === '') {
            $this->addFlash('error', 'OIDC callback is missing authorization code.');
            $this->safeCreateWebLog(
                ActionLogAction::AUTH_CALLBACK,
                ActionLog::LEVEL_ERROR,
                'OIDC callback failed: missing authorization code.'
            );

            return $this->redirectToRoute('hat_auth_login');
        }

        $nextPath = $this->normalizeNextPath((string)$session->get(self::OIDC_SESSION_NEXT_KEY, '/'));
        $session->remove(self::OIDC_SESSION_NEXT_KEY);

        try {
            $accessToken = $this->oidcClient->exchangeCodeForAccessToken($code);
            $userInfo = $this->oidcClient->fetchUserInfo($accessToken);

            $preferredUsername = trim((string)($userInfo['preferred_username'] ?? ''));
            $subject = trim((string)($userInfo['sub'] ?? ''));
            if ($preferredUsername === '' || $subject === '') {
                throw new \RuntimeException('OIDC user info is missing preferred_username or sub.');
            }

            $user = $this->authUserService->createOrUpdateFromOidc($subject, $preferredUsername);
            $issuedToken = $this->jwtTokenService->issueToken($user);

            $response = new RedirectResponse($nextPath);
            $response->headers->setCookie(
                $this->jwtTokenService->createAuthCookie($issuedToken, $request->isSecure())
            );

            $this->safeCreateWebLog(
                ActionLogAction::AUTH_CALLBACK,
                ActionLog::LEVEL_INFO,
                sprintf("OIDC login success for user '%s'.", $user->getUsername())
            );

            return $response;
        } catch (Throwable $exception) {
            $this->addFlash('error', sprintf('OIDC login failed: %s', $exception->getMessage()));
            $this->safeCreateWebLog(
                ActionLogAction::AUTH_CALLBACK,
                ActionLog::LEVEL_ERROR,
                sprintf('OIDC login failed: %s', $exception->getMessage())
            );

            return $this->redirectToRoute('hat_auth_login');
        }
    }

    #[Route(path: '/logout', name: 'hat_auth_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $csrfToken = (string)$request->request->get('_token', '');
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_LOGOUT, $csrfToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            $this->safeCreateWebLog(
                ActionLogAction::AUTH_LOGOUT,
                ActionLog::LEVEL_WARNING,
                'Logout blocked: invalid CSRF token.'
            );

            return $this->redirectToRoute('hat_dashboard');
        }

        $response = $this->redirectToRoute('hat_auth_login');
        $response->headers->setCookie($this->jwtTokenService->createClearedCookie($request->isSecure()));

        $this->safeCreateWebLog(ActionLogAction::AUTH_LOGOUT, ActionLog::LEVEL_INFO, 'User logged out.');

        return $response;
    }

    protected function startOidcLogin(Request $request, string $nextPath): Response
    {
        try {
            $state = bin2hex(random_bytes(32));
        } catch (RandomException $exception) {
            $this->addFlash('error', sprintf('OIDC login failed: %s', $exception->getMessage()));
            $this->safeCreateWebLog(
                ActionLogAction::AUTH_LOGIN,
                ActionLog::LEVEL_ERROR,
                sprintf('OIDC login failed: %s', $exception->getMessage())
            );

            return $this->redirectToRoute('hat_auth_login');
        }

        $request->getSession()->set(self::OIDC_SESSION_STATE_KEY, $state);
        $request->getSession()->set(self::OIDC_SESSION_NEXT_KEY, $nextPath);

        try {
            return new RedirectResponse($this->oidcClient->buildAuthorizationUrl($state));
        } catch (Throwable $exception) {
            $request->getSession()->remove(self::OIDC_SESSION_STATE_KEY);
            $request->getSession()->remove(self::OIDC_SESSION_NEXT_KEY);
            $this->addFlash('error', sprintf('OIDC login failed: %s', $exception->getMessage()));
            $this->safeCreateWebLog(
                ActionLogAction::AUTH_LOGIN,
                ActionLog::LEVEL_ERROR,
                sprintf('OIDC login failed: %s', $exception->getMessage())
            );

            return $this->redirectToRoute('hat_auth_login');
        }
    }

    protected function normalizeNextPath(string $candidate): string
    {
        if ($candidate === '' || !str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return $this->generateUrl('hat_dashboard');
        }

        return $candidate;
    }

    protected function safeCreateWebLog(string|ActionLogAction $action, string $level, string $message): void
    {
        try {
            $resolvedAction = $action instanceof ActionLogAction ? $action->value : $action;
            $this->actionLogService->createActionLog(ActionLog::SOURCE_WEB, $resolvedAction, $level, $message);
        } catch (Throwable) {
        }
    }
}
