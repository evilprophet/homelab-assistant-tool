<?php

declare(strict_types=1);

namespace EvilStudio\HAT\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    // No script/style directives: the base layout still carries inline blocks.
    protected const string CONTENT_SECURITY_POLICY = "frame-ancestors 'none'; base-uri 'self'; form-action 'self'";

    protected const array HEADERS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        foreach (self::HEADERS as $name => $value) {
            if ($headers->has($name)) {
                continue;
            }

            $headers->set($name, $value);
        }
    }
}
