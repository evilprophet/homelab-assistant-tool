<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\EventSubscriber;

use EvilStudio\HAT\EventSubscriber\WebRequestLogSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class WebRequestLogSubscriberTest extends TestCase
{
    public function testOnRequestStoresStartTimeAndLogsRequestStart(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/devices?foo=bar', 'GET');

        $logger->expects($this->once())
            ->method('info')
            ->with('Request started', $this->arrayHasKey('path'));

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber = new WebRequestLogSubscriber($logger);
        $subscriber->onRequest($event);

        $this->assertIsFloat($request->attributes->get('_hat_request_started_at'));
    }

    public function testOnRequestRedactsSensitiveQueryParameters(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/auth/callback?code=abc&state=def&next=%2Fdevices', 'GET');

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Request started',
                $this->callback(static function (array $context): bool {
                    $query = $context['query'] ?? null;
                    if (!is_array($query)) {
                        return false;
                    }

                    return ($query['code'] ?? null) === '<redacted>'
                        && ($query['state'] ?? null) === '<redacted>'
                        && ($query['next'] ?? null) === '/devices';
                })
            );

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber = new WebRequestLogSubscriber($logger);
        $subscriber->onRequest($event);
    }

    public function testOnResponseLogsRequestFinishedWithDuration(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/devices', 'GET');
        $request->attributes->set('_hat_request_started_at', microtime(true) - 0.2);
        $response = new Response('ok', 200);

        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Request finished',
                $this->callback(static function (array $context): bool {
                    return array_key_exists('duration_ms', $context) && is_int($context['duration_ms']);
                })
            );

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber = new WebRequestLogSubscriber($logger);
        $subscriber->onResponse($event);
    }

    public function testOnExceptionLogsUnhandledException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/devices', 'GET');
        $exception = new RuntimeException('Failure');

        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Unhandled request exception',
                $this->callback(static function (array $context): bool {
                    return ($context['exception'] ?? null) === RuntimeException::class
                        && ($context['message'] ?? null) === 'Failure';
                })
            );

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
        $subscriber = new WebRequestLogSubscriber($logger);
        $subscriber->onException($event);
    }
}
