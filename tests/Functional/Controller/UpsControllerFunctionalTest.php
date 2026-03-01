<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Functional\Controller;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Functional\Support\HttpFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

class UpsControllerFunctionalTest extends HttpFunctionalTestCase
{
    public function testNewShowsValidationErrorsForInvalidPayload(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/ups');

        $response = $this->request('POST', '/ups/new', [
            'name' => '',
            'identifier' => '',
            'host' => '',
            'safe_threshold_minutes' => '-1',
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = html_entity_decode((string)$response->getContent(), ENT_QUOTES);
        $this->assertStringContainsString('UPS name is required.', $content);
        $this->assertStringContainsString('UPS identifier is required.', $content);
        $this->assertStringContainsString('UPS host is required.', $content);
        $this->assertStringContainsString('Safe threshold must be a non-negative integer.', $content);
        $this->assertCount(0, static::getContainer()->get(UpsService::class)->listUps());
    }

    public function testNewCreatesUpsAndRedirectsToIndex(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/ups');

        $response = $this->request('POST', '/ups/new', [
            'name' => 'Main UPS',
            'identifier' => 'ups-main',
            'host' => '127.0.0.1',
            'safe_threshold_minutes' => '10',
        ]);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/ups', $response->headers->get('Location'));

        $ups = static::getContainer()->get(UpsService::class)->getUpsByIdentifier('ups-main');
        $this->assertSame('Main UPS', $ups->getName());
        $this->assertSame(600, $ups->getSafeBatteryRuntimeThreshold());
    }

    public function testEditUpdatesUpsAndRedirectsToEditPage(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/ups');

        $upsService = static::getContainer()->get(UpsService::class);
        $ups = $upsService->createUps('Primary UPS', 'ups-primary', '10.0.0.2', 600);

        $response = $this->request('POST', sprintf('/ups/%d/edit', (int)$ups->getId()), [
            'name' => 'Primary UPS Updated',
            'identifier' => 'ups-primary-updated',
            'host' => '10.0.0.20',
            'safe_threshold_minutes' => '15',
        ]);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame(
            sprintf('/ups/%d/edit', (int)$ups->getId()),
            $response->headers->get('Location')
        );

        $updatedUps = $upsService->getUpsById((int)$ups->getId());
        $this->assertSame('Primary UPS Updated', $updatedUps->getName());
        $this->assertSame('ups-primary-updated', $updatedUps->getIdentifier());
        $this->assertSame('10.0.0.20', $updatedUps->getHost());
        $this->assertSame(900, $updatedUps->getSafeBatteryRuntimeThreshold());
    }

    public function testRemoveDeletesUpsAndDetachesLinkedDevices(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/ups');

        $upsService = static::getContainer()->get(UpsService::class);
        $deviceService = static::getContainer()->get(DeviceService::class);

        $ups = $upsService->createUps('UPS To Remove', 'ups-remove', '10.0.0.50', 300);
        $device = $deviceService->createDevice(
            'node-linked',
            '10.0.0.40',
            '00:11:22:aa:bb:cc',
            DevicePlatform::GENERIC->value,
            null,
            null,
            (int)$ups->getId()
        );

        $confirmResponse = $this->request('GET', sprintf('/ups/%d/remove', (int)$ups->getId()));
        $this->assertSame(Response::HTTP_OK, $confirmResponse->getStatusCode());
        $this->assertStringContainsString('node-linked', (string)$confirmResponse->getContent());

        $removeResponse = $this->request('POST', sprintf('/ups/%d/remove', (int)$ups->getId()));
        $this->assertSame(Response::HTTP_FOUND, $removeResponse->getStatusCode());
        $this->assertSame('/ups', $removeResponse->headers->get('Location'));

        $this->assertCount(0, $upsService->listUps());
        $updatedDevice = $deviceService->getDeviceById((int)$device->getId());
        $this->assertNull($updatedDevice->getUps());
    }

    public function testIndexNormalizesOutOfRangePageToLastPage(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/ups');

        $upsService = static::getContainer()->get(UpsService::class);
        for ($index = 1; $index <= 30; $index++) {
            $upsService->createUps(
                sprintf('UPS %02d', $index),
                sprintf('ups-%02d', $index),
                sprintf('10.40.0.%d', $index),
                300
            );
        }

        $response = $this->request('GET', '/ups?page=999');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string)$response->getContent();
        $this->assertStringContainsString('Showing page 2 / 2 (30 results)', $content);
        $this->assertStringContainsString('ups-30', $content);
        $this->assertStringNotContainsString('ups-01', $content);
    }

    public function testRemoveUnknownUpsShowsErrorFlashAndRedirects(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/ups');

        $removeResponse = $this->request('POST', '/ups/999/remove');
        $this->assertSame(Response::HTTP_FOUND, $removeResponse->getStatusCode());
        $this->assertSame('/ups', $removeResponse->headers->get('Location'));

        $indexResponse = $this->request('GET', '/ups');
        $this->assertSame(Response::HTTP_OK, $indexResponse->getStatusCode());
        $content = html_entity_decode((string)$indexResponse->getContent(), ENT_QUOTES);
        $this->assertStringContainsString("UPS with id '999' not found.", $content);
    }
}
