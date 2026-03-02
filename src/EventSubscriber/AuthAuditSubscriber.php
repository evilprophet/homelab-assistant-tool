<?php

declare(strict_types=1);

namespace EvilStudio\HAT\EventSubscriber;

use EvilStudio\HAT\Contract\ActionLogAction;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use Throwable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthAuditSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function __construct(
        protected ActionLogService $actionLogService
    ) {
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        $action = $path === '/auth/callback'
            ? ActionLogAction::AUTH_CALLBACK
            : ActionLogAction::AUTH_LOGIN;

        $this->safeCreateWebLog(
            $action,
            ActionLog::LEVEL_INFO,
            sprintf("Authentication success for user '%s'.", $event->getUser()->getUserIdentifier())
        );
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $username = $this->extractUsernameFromFailureEvent($event);

        $this->safeCreateWebLog(
            ActionLogAction::AUTH_LOGIN,
            ActionLog::LEVEL_WARNING,
            sprintf(
                "Authentication failed for username '%s'.",
                $username === '' ? '<empty>' : $username
            )
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        $username = $user instanceof UserInterface ? $user->getUserIdentifier() : 'unknown';

        $this->safeCreateWebLog(
            ActionLogAction::AUTH_LOGOUT,
            ActionLog::LEVEL_INFO,
            sprintf("User '%s' logged out.", $username)
        );
    }

    protected function extractUsernameFromFailureEvent(LoginFailureEvent $event): string
    {
        $passport = $event->getPassport();
        if ($passport === null) {
            return trim((string)$event->getRequest()->request->get('username', ''));
        }

        $userBadge = $passport->getBadge(UserBadge::class);
        if (!$userBadge instanceof UserBadge) {
            return trim((string)$event->getRequest()->request->get('username', ''));
        }

        return trim($userBadge->getUserIdentifier());
    }

    protected function safeCreateWebLog(ActionLogAction $action, string $level, string $message): void
    {
        try {
            $this->actionLogService->createActionLog(ActionLog::SOURCE_WEB, $action->value, $level, $message);
        } catch (Throwable) {
        }
    }
}
