<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\EventSubscriber;

use EvilStudio\HAT\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriberTest extends TestCase
{
    public function testSubscribesToTheResponseEvent(): void
    {
        $this->assertSame(
            [KernelEvents::RESPONSE => 'onResponse'],
            SecurityHeadersSubscriber::getSubscribedEvents()
        );
    }

    public function testSetsAllSecurityHeadersOnAMainRequest(): void
    {
        $response = $this->dispatch(new Response(), HttpKernelInterface::MAIN_REQUEST);

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('same-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame(
            "frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
            $response->headers->get('Content-Security-Policy')
        );
    }

    public function testLeavesSubRequestsAlone(): void
    {
        $response = $this->dispatch(new Response(), HttpKernelInterface::SUB_REQUEST);

        $this->assertFalse($response->headers->has('X-Frame-Options'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function testDoesNotOverwriteAHeaderTheResponseAlreadySet(): void
    {
        $response = new Response();
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        $this->dispatch($response, HttpKernelInterface::MAIN_REQUEST);

        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    protected function dispatch(Response $response, int $requestType): Response
    {
        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/devices'),
            $requestType,
            $response
        );

        (new SecurityHeadersSubscriber())->onResponse($event);

        return $event->getResponse();
    }
}
