<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Functional\Controller;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Tests\Functional\Support\HttpFunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

class ScheduleControllerFunctionalTest extends HttpFunctionalTestCase
{
    public function testNewShowsValidationErrorsForInvalidPayload(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/schedules');

        $response = $this->request('POST', '/schedules/new', [
            'name' => '',
            'cron_expression' => 'invalid-cron',
            'command' => 'invalid-command',
            'is_enabled' => '1',
            'device_ids' => ['999999'],
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = html_entity_decode((string)$response->getContent(), ENT_QUOTES);
        $this->assertStringContainsString('Schedule name is required.', $content);
        $this->assertStringContainsString('Cron expression is invalid.', $content);
        $this->assertStringContainsString('Command must be one of: start, stop.', $content);
        $this->assertStringContainsString('Selected device ID 999999 does not exist.', $content);
        $this->assertCount(0, static::getContainer()->get(ScheduleService::class)->listSchedules());
    }

    public function testNewCreatesScheduleAndRedirectsToIndex(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/schedules');

        $device = static::getContainer()->get(DeviceService::class)->createDevice(
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::GENERIC->value
        );

        $response = $this->request('POST', '/schedules/new', [
            'name' => 'Night Start',
            'cron_expression' => '* * * * *',
            'command' => ScheduleInterface::COMMAND_START,
            'is_enabled' => '1',
            'device_ids' => [(string)$device->getId()],
        ]);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/schedules', $response->headers->get('Location'));

        $schedules = static::getContainer()->get(ScheduleService::class)->listSchedules();
        $this->assertCount(1, $schedules);
        $this->assertSame('Night Start', $schedules[0]->getName());
        $this->assertSame(ScheduleInterface::COMMAND_START, $schedules[0]->getCommand());
        $this->assertCount(1, $schedules[0]->getDevices());
    }

    public function testEditUpdatesScheduleAndDeviceLinks(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/schedules');

        $deviceService = static::getContainer()->get(DeviceService::class);
        $scheduleService = static::getContainer()->get(ScheduleService::class);

        $firstDevice = $deviceService->createDevice(
            'node-first',
            '10.0.0.11',
            '00:11:22:33:44:11',
            DevicePlatform::GENERIC->value
        );
        $secondDevice = $deviceService->createDevice(
            'node-second',
            '10.0.0.12',
            '00:11:22:33:44:12',
            DevicePlatform::GENERIC->value
        );

        $schedule = $scheduleService->createSchedule(
            'Night Action',
            '* * * * *',
            ScheduleInterface::COMMAND_START,
            [(int)$firstDevice->getId()],
            true
        );

        $response = $this->request('POST', sprintf('/schedules/%d/edit', (int)$schedule->getId()), [
            'name' => 'Night Action Updated',
            'cron_expression' => '*/5 * * * *',
            'command' => ScheduleInterface::COMMAND_STOP,
            'is_enabled' => '0',
            'device_ids' => [(string)$secondDevice->getId()],
        ]);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame(
            sprintf('/schedules/%d/edit', (int)$schedule->getId()),
            $response->headers->get('Location')
        );

        $updatedSchedule = $scheduleService->getScheduleById((int)$schedule->getId());
        $this->assertSame('Night Action Updated', $updatedSchedule->getName());
        $this->assertSame('*/5 * * * *', $updatedSchedule->getCronExpression());
        $this->assertSame(ScheduleInterface::COMMAND_STOP, $updatedSchedule->getCommand());
        $this->assertFalse($updatedSchedule->isEnabled());
        $this->assertCount(1, $updatedSchedule->getDevices());
        $this->assertSame((int)$secondDevice->getId(), $updatedSchedule->getDevices()->first()->getId());
    }

    public function testRemoveDeletesScheduleAndDetachesDevices(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/schedules');

        $deviceService = static::getContainer()->get(DeviceService::class);
        $scheduleService = static::getContainer()->get(ScheduleService::class);

        $device = $deviceService->createDevice(
            'node-with-schedule',
            '10.0.0.13',
            '00:11:22:33:44:13',
            DevicePlatform::GENERIC->value
        );
        $schedule = $scheduleService->createSchedule(
            'Schedule To Remove',
            '* * * * *',
            ScheduleInterface::COMMAND_START,
            [(int)$device->getId()],
            true
        );

        $confirmResponse = $this->request('GET', sprintf('/schedules/%d/remove', (int)$schedule->getId()));
        $this->assertSame(Response::HTTP_OK, $confirmResponse->getStatusCode());
        $this->assertStringContainsString('node-with-schedule', (string)$confirmResponse->getContent());

        $removeResponse = $this->request('POST', sprintf('/schedules/%d/remove', (int)$schedule->getId()));
        $this->assertSame(Response::HTTP_FOUND, $removeResponse->getStatusCode());
        $this->assertSame('/schedules', $removeResponse->headers->get('Location'));

        $this->assertCount(0, $scheduleService->listSchedules());

        $updatedDevice = $deviceService->getDeviceById((int)$device->getId());
        $this->assertCount(0, $updatedDevice->getSchedules());
    }

    public function testIndexNormalizesOutOfRangePageToLastPage(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/schedules');

        $device = static::getContainer()->get(DeviceService::class)->createDevice(
            'node-pagination',
            '10.50.0.1',
            '00:11:22:33:66:77',
            DevicePlatform::GENERIC->value
        );

        $scheduleService = static::getContainer()->get(ScheduleService::class);
        for ($index = 1; $index <= 30; $index++) {
            $scheduleService->createSchedule(
                sprintf('schedule-%02d', $index),
                '* * * * *',
                ScheduleInterface::COMMAND_START,
                [(int)$device->getId()],
                true
            );
        }

        $response = $this->request('GET', '/schedules?page=999');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string)$response->getContent();
        $this->assertStringContainsString('Showing page 2 / 2 (30 results)', $content);
        $this->assertStringContainsString('schedule-30', $content);
        $this->assertStringNotContainsString('schedule-01', $content);
    }

    public function testRemoveUnknownScheduleShowsErrorFlashAndRedirects(): void
    {
        $this->createSimpleUser('admin', 'secret-1');
        $this->loginAsSimpleUser('admin', 'secret-1', '/schedules');

        $removeResponse = $this->request('POST', '/schedules/999/remove');
        $this->assertSame(Response::HTTP_FOUND, $removeResponse->getStatusCode());
        $this->assertSame('/schedules', $removeResponse->headers->get('Location'));

        $indexResponse = $this->request('GET', '/schedules');
        $this->assertSame(Response::HTTP_OK, $indexResponse->getStatusCode());
        $content = html_entity_decode((string)$indexResponse->getContent(), ENT_QUOTES);
        $this->assertStringContainsString("Schedule with id '999' not found.", $content);
    }
}
