<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Functional\Controller;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Functional\Support\HttpFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

class CsrfProtectionFunctionalTest extends HttpFunctionalTestCase
{
    public function testDeviceCreationIsRejectedWithoutAValidToken(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $response = $this->request('POST', '/devices/new', [
            'name' => 'csrf-device',
            'ip' => '10.0.0.90',
            'mac' => '00:11:22:33:44:90',
            'platform' => DevicePlatform::GENERIC->value,
            'username' => '',
            'ups_id' => '',
            'threshold_minutes' => '',
            '_token' => 'not-a-valid-token',
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Invalid CSRF token.', (string)$response->getContent());

        $deviceService = static::getContainer()->get(DeviceService::class);
        $this->assertSame([], $deviceService->listDevices());
    }

    public function testUpsCreationIsRejectedWithoutAValidToken(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/ups');

        $response = $this->request('POST', '/ups/new', [
            'name' => 'csrf-ups',
            'identifier' => 'csrf-ups',
            'host' => 'ups.local',
            'safe_threshold_minutes' => '',
            '_token' => '',
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('Invalid CSRF token.', (string)$response->getContent());

        $upsService = static::getContainer()->get(UpsService::class);
        $this->assertSame([], $upsService->listUps());
    }

    public function testLogoutIsRejectedWithoutAValidToken(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $response = $this->request('POST', '/auth/logout', ['_token' => 'not-a-valid-token']);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testProtectedRoutesRedirectGuestsToLogin(): void
    {
        foreach (['/logs', '/ups', '/schedules', '/runtime/statuses'] as $path) {
            $response = $this->request('GET', $path);

            $this->assertSame(
                Response::HTTP_FOUND,
                $response->getStatusCode(),
                sprintf("Guest access to '%s' was not redirected.", $path)
            );
            $this->assertStringContainsString(
                '/auth/login',
                (string)$response->headers->get('Location'),
                sprintf("Guest access to '%s' did not redirect to the login page.", $path)
            );
        }
    }
}
