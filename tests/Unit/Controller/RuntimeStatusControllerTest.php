<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Controller;

use EvilStudio\HAT\Controller\RuntimeStatusController;
use EvilStudio\HAT\Service\Runtime\RuntimeStatusResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

class RuntimeStatusControllerTest extends TestCase
{
    public function testStatusesReturnsResolvedDeviceAndUpsStatusWithCacheHeaders(): void
    {
        $resolver = $this->createMock(RuntimeStatusResolver::class);

        $resolver->expects($this->once())
            ->method('resolveDeviceStatusByNames')
            ->with(['node-1'])
            ->willReturn(['node-1' => 'online']);
        $resolver->expects($this->once())
            ->method('resolveUpsStatusByIdentifiers')
            ->with(['ups-main'])
            ->willReturn([
                'ups-main' => [
                    'label' => 'Online',
                    'tone' => 'success',
                    'battery_level' => 98,
                    'battery_runtime_minutes' => 120,
                ],
            ]);

        $controller = new RuntimeStatusController($resolver);
        $controller->setContainer(new ContainerBuilder());
        $request = Request::create('/runtime/statuses', 'GET', [
            'device_names' => ['node-1'],
            'ups_identifiers' => ['ups-main'],
        ]);

        $response = $controller->statuses($request);
        $decoded = json_decode($response->getContent() ?: '', true);

        $this->assertIsArray($decoded);
        $this->assertArrayNotHasKey('ups_data_by_identifier', $decoded);
        $this->assertSame(['node-1' => 'online'], $decoded['device_status_by_name'] ?? []);
        $this->assertSame('Online', $decoded['ups_status_by_identifier']['ups-main']['label'] ?? null);
        $this->assertStringContainsString('max-age=60', (string)$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string)$response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', (string)$response->headers->get('Cache-Control'));
    }
}
