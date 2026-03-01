<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Functional\Controller;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Service\Runtime\RuntimeStatusResolver;
use EvilStudio\HAT\Tests\Functional\Support\HttpFunctionalTestCase;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class HomeAndRuntimeControllerFunctionalTest extends HttpFunctionalTestCase
{
    /**
     * @throws JsonException
     */
    public function testDashboardRendersCurrentEntitiesAndRecentLog(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/');

        $deviceService = static::getContainer()->get(DeviceService::class);
        $upsService = static::getContainer()->get(UpsService::class);
        $scheduleService = static::getContainer()->get(ScheduleService::class);
        $actionLogService = static::getContainer()->get(ActionLogService::class);

        $ups = $upsService->createUps('Dashboard UPS', 'ups-dashboard', '127.0.0.1', 600);
        $device = $deviceService->createDevice(
            'dashboard-node',
            '10.0.0.60',
            '00:11:22:33:44:66',
            DevicePlatform::GENERIC->value,
            null,
            null,
            (int)$ups->getId()
        );
        $scheduleService->createSchedule(
            'Dashboard Schedule',
            '* * * * *',
            ScheduleInterface::COMMAND_START,
            [(int)$device->getId()],
            true
        );
        $actionLogService->createActionLog(
            ActionLog::SOURCE_WEB,
            'dashboard.test',
            ActionLog::LEVEL_INFO,
            'dashboard-log-message-unique'
        );

        $response = $this->request('GET', '/');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('dashboard-node', (string)$response->getContent());
        $this->assertStringContainsString('Dashboard UPS', (string)$response->getContent());
        $this->assertStringContainsString('Dashboard Schedule', (string)$response->getContent());
        $this->assertStringContainsString('dashboard-log-message-unique', (string)$response->getContent());
        $this->assertStringContainsString('INFO', (string)$response->getContent());
        $this->assertStringContainsString('dashboard.test', (string)$response->getContent());
    }

    /**
     * @throws JsonException
     */
    public function testRuntimeStatusesReturnsUnknownForMissingIdentifiers(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/');

        $response = $this->request(
            'GET',
            '/runtime/statuses?device_names[]=missing-node&ups_identifiers[]=missing-ups'
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('unknown', $payload['device_status_by_name']['missing-node']);
        $this->assertSame('Unknown', $payload['ups_status_by_identifier']['missing-ups']['label']);
        $this->assertSame('neutral', $payload['ups_status_by_identifier']['missing-ups']['tone']);
        $this->assertNull($payload['ups_status_by_identifier']['missing-ups']['battery_level']);
        $this->assertNull($payload['ups_status_by_identifier']['missing-ups']['battery_runtime_minutes']);
    }

    /**
     * @throws JsonException
     */
    public function testRuntimeStatusesReturnsSuccessPayloadFromDeterministicRuntimeResolverStub(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/');

        $runtimeStatusResolver = $this->createMock(RuntimeStatusResolver::class);
        $runtimeStatusResolver->expects($this->once())
            ->method('resolveDeviceStatusByNames')
            ->with(['node-online'])
            ->willReturn(['node-online' => 'online']);
        $runtimeStatusResolver->expects($this->once())
            ->method('resolveUpsStatusByIdentifiers')
            ->with(['ups-main'])
            ->willReturn([
                'ups-main' => [
                    'label' => 'Online',
                    'tone' => 'success',
                    'battery_level' => 95,
                    'battery_runtime_minutes' => 42,
                ],
            ]);
        static::getContainer()->set(RuntimeStatusResolver::class, $runtimeStatusResolver);

        $response = $this->request(
            'GET',
            '/runtime/statuses?device_names[]=node-online&ups_identifiers[]=ups-main'
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('online', $payload['device_status_by_name']['node-online']);
        $this->assertSame('Online', $payload['ups_status_by_identifier']['ups-main']['label']);
        $this->assertSame('success', $payload['ups_status_by_identifier']['ups-main']['tone']);
        $this->assertSame(95, $payload['ups_status_by_identifier']['ups-main']['battery_level']);
        $this->assertSame(42, $payload['ups_status_by_identifier']['ups-main']['battery_runtime_minutes']);
    }
}
