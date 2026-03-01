<?php

declare(strict_types=1);

namespace EvilStudio\HAT\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class WebRequestLogSubscriber implements EventSubscriberInterface
{
    protected const string REQUEST_START_ATTRIBUTE = '_hat_request_started_at';
    protected const string REDACTED_VALUE = '<redacted>';
    protected const array SENSITIVE_QUERY_KEYS = [
        'access_token',
        'assertion',
        'authorization',
        'client_secret',
        'code',
        'id_token',
        'password',
        'refresh_token',
        'secret',
        'state',
        'token',
    ];

    public function __construct(
        protected LoggerInterface $webLogger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onRequest',
            KernelEvents::RESPONSE => 'onResponse',
            KernelEvents::EXCEPTION => 'onException',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $request->attributes->set(self::REQUEST_START_ATTRIBUTE, microtime(true));

        $this->webLogger->info('Request started', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'query' => $this->sanitizeQueryParameters($request->query->all()),
            'client_ip' => $request->getClientIp(),
        ]);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $startedAt = $request->attributes->get(self::REQUEST_START_ATTRIBUTE);
        $durationMs = is_float($startedAt)
            ? (int)round((microtime(true) - $startedAt) * 1000)
            : null;

        $this->webLogger->info('Request finished', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'status_code' => $event->getResponse()->getStatusCode(),
            'duration_ms' => $durationMs,
        ]);
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $exception = $event->getThrowable();

        $this->webLogger->error('Unhandled request exception', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
    }

    protected function sanitizeQueryParameters(array $queryParameters): array
    {
        $sanitizedParameters = [];

        foreach ($queryParameters as $key => $value) {
            $normalizedKey = mb_strtolower((string)$key);

            if ($this->isSensitiveQueryKey($normalizedKey)) {
                $sanitizedParameters[(string)$key] = self::REDACTED_VALUE;
                continue;
            }

            if (is_array($value)) {
                $sanitizedParameters[(string)$key] = $this->sanitizeQueryParameters($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitizedParameters[(string)$key] = $value;
            }
        }

        return $sanitizedParameters;
    }

    protected function isSensitiveQueryKey(string $key): bool
    {
        foreach (self::SENSITIVE_QUERY_KEYS as $sensitiveKey) {
            if ($key === $sensitiveKey || str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
