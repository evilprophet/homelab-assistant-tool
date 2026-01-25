<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Model;

use DateTime;
use EvilStudio\HAT\Model\Schedule;
use PHPUnit\Framework\TestCase;

class ScheduleTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $schedule = new Schedule(
            name: 'Test Schedule',
            schedule: '0 0 * * *',
            command: 'start',
            deviceCodes: ['device1', 'device2']
        );

        $this->assertEquals('Test Schedule', $schedule->getName());
        $this->assertEquals('0 0 * * *', $schedule->getSchedule());
        $this->assertEquals('start', $schedule->getCommand());
        $this->assertEquals(['device1', 'device2'], $schedule->getDeviceCodes());
        $this->assertNull($schedule->isCronScheduleMatching());
    }

    public function testToArray(): void
    {
        $schedule = new Schedule(
            name: 'Test Schedule',
            schedule: '0 0 * * *',
            command: 'start',
            deviceCodes: ['device1', 'device2']
        );

        $expected = [
            'name' => 'Test Schedule',
            'schedule' => '0 0 * * *',
            'command' => 'start',
            'devices' => 'device1, device2',
        ];

        $this->assertEquals($expected, $schedule->toArray());
    }

    public function testCheckCronScheduleWhenJobShouldRun(): void
    {
        $schedule = new Schedule(
            name: 'Every Minute Job',
            schedule: '* * * * *',
            command: 'start',
            deviceCodes: ['device1']
        );

        $dateTime = new DateTime('2024-01-15 10:30:00');
        $schedule->checkCronSchedule($dateTime);

        $this->assertTrue($schedule->isCronScheduleMatching());
    }

    public function testCheckCronScheduleWhenJobShouldNotRun(): void
    {
        $schedule = new Schedule(
            name: 'New Year Job',
            schedule: '0 0 1 1 *',
            command: 'start',
            deviceCodes: ['device1']
        );

        $dateTime = new DateTime('2024-06-15 10:30:00');
        $schedule->checkCronSchedule($dateTime);

        $this->assertFalse($schedule->isCronScheduleMatching());
    }

    public function testCheckCronScheduleAtExactTime(): void
    {
        $schedule = new Schedule(
            name: 'Daily 10am Job',
            schedule: '0 10 * * *',
            command: 'start',
            deviceCodes: ['device1']
        );

        $dateTime = new DateTime('2024-01-15 10:00:00');
        $schedule->checkCronSchedule($dateTime);

        $this->assertTrue($schedule->isCronScheduleMatching());
    }

    public function testCheckCronScheduleWithinOneMinuteWindow(): void
    {
        $schedule = new Schedule(
            name: 'Daily 10am Job',
            schedule: '0 10 * * *',
            command: 'start',
            deviceCodes: ['device1']
        );

        $dateTime = new DateTime('2024-01-15 10:01:00');
        $schedule->checkCronSchedule($dateTime);

        $this->assertTrue($schedule->isCronScheduleMatching());

        $schedule2 = new Schedule(
            name: 'Daily 10am Job',
            schedule: '0 10 * * *',
            command: 'start',
            deviceCodes: ['device1']
        );
        $dateTime2 = new DateTime('2024-01-15 09:59:00');
        $schedule2->checkCronSchedule($dateTime2);

        $this->assertTrue($schedule2->isCronScheduleMatching());
    }

    public function testCheckCronScheduleOutsideWindow(): void
    {
        $schedule = new Schedule(
            name: 'Daily 10am Job',
            schedule: '0 10 * * *',
            command: 'start',
            deviceCodes: ['device1']
        );

        $dateTime = new DateTime('2024-01-15 10:05:00');
        $schedule->checkCronSchedule($dateTime);

        $this->assertFalse($schedule->isCronScheduleMatching());
    }

    public function testCheckCronScheduleWithMultipleDevices(): void
    {
        $schedule = new Schedule(
            name: 'Multi Device Job',
            schedule: '*/5 * * * *',
            command: 'stop',
            deviceCodes: ['device1', 'device2', 'device3']
        );

        $dateTime = new DateTime('2024-01-15 10:00:00');
        $schedule->checkCronSchedule($dateTime);

        $this->assertTrue($schedule->isCronScheduleMatching());
        $this->assertCount(3, $schedule->getDeviceCodes());
    }
}
