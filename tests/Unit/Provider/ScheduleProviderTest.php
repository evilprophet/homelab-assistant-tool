<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Provider;

use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Model\Schedule;
use EvilStudio\HAT\Provider\ScheduleProvider;
use PHPUnit\Framework\TestCase;

class ScheduleProviderTest extends TestCase
{
    protected Configuration $configuration;

    protected function setUp(): void
    {
        $config = [
            'cron' => true,
            'ups_mode' => false,
            'ssh_key_path' => '/path/to/key',
            'default_ssh_username' => 'testuser',
            'timezone' => 'UTC',
        ];

        $this->configuration = new Configuration($config);
    }

    public function testConstructorWithEmptySchedulesData(): void
    {
        $provider = new ScheduleProvider($this->configuration, []);

        $this->assertInstanceOf(ScheduleProvider::class, $provider);
        $this->assertEmpty($provider->getScheduleList());
    }

    public function testConstructorWithSchedulesData(): void
    {
        $schedulesData = [
            [
                'name' => 'Morning Start',
                'schedule' => '0 8 * * *',
                'command' => 'start',
                'devices' => ['device1', 'device2'],
            ],
            [
                'name' => 'Evening Stop',
                'schedule' => '0 22 * * *',
                'command' => 'stop',
                'devices' => ['device1'],
            ],
        ];

        $provider = new ScheduleProvider($this->configuration, $schedulesData);
        $schedules = $provider->getScheduleList();

        $this->assertCount(2, $schedules);
        $this->assertInstanceOf(Schedule::class, $schedules[0]);
        $this->assertInstanceOf(Schedule::class, $schedules[1]);
    }

    public function testGetScheduleListReturnsCorrectData(): void
    {
        $schedulesData = [
            [
                'name' => 'Test Schedule',
                'schedule' => '*/5 * * * *',
                'command' => 'start',
                'devices' => ['device1'],
            ],
        ];

        $provider = new ScheduleProvider($this->configuration, $schedulesData);
        $schedules = $provider->getScheduleList();

        $this->assertCount(1, $schedules);

        $schedule = $schedules[0];
        $this->assertEquals('Test Schedule', $schedule->getName());
        $this->assertEquals('*/5 * * * *', $schedule->getSchedule());
        $this->assertEquals('start', $schedule->getCommand());
        $this->assertEquals(['device1'], $schedule->getDeviceCodes());
    }

    public function testGetScheduleListWithMultipleDevices(): void
    {
        $schedulesData = [
            [
                'name' => 'Multi Device Schedule',
                'schedule' => '0 0 * * *',
                'command' => 'stop',
                'devices' => ['device1', 'device2', 'device3'],
            ],
        ];

        $provider = new ScheduleProvider($this->configuration, $schedulesData);
        $schedules = $provider->getScheduleList();
        $schedule = $schedules[0];

        $this->assertCount(3, $schedule->getDeviceCodes());
        $this->assertEquals(['device1', 'device2', 'device3'], $schedule->getDeviceCodes());
    }

    public function testGetProperties(): void
    {
        $provider = new ScheduleProvider($this->configuration, []);
        $properties = $provider->getProperties();

        $this->assertEquals(['Name', 'Schedule', 'Command', 'Devices'], $properties);
    }

    public function testCheckAllCronSchedule(): void
    {
        $schedulesData = [
            [
                'name' => 'Every Minute',
                'schedule' => '* * * * *',
                'command' => 'start',
                'devices' => ['device1'],
            ],
            [
                'name' => 'Never Runs',
                'schedule' => '0 0 1 1 *',
                'command' => 'stop',
                'devices' => ['device2'],
            ],
        ];

        $provider = new ScheduleProvider($this->configuration, $schedulesData);
        $provider->checkAllCronSchedule();

        $schedules = $provider->getScheduleList();

        $this->assertTrue($schedules[0]->isCronScheduleMatching());
        $this->assertFalse($schedules[1]->isCronScheduleMatching());
    }

    public function testCheckAllCronScheduleWithEmptyList(): void
    {
        $provider = new ScheduleProvider($this->configuration, []);

        $provider->checkAllCronSchedule();

        $this->assertEmpty($provider->getScheduleList());
    }

    public function testScheduleOrderIsPreserved(): void
    {
        $schedulesData = [
            ['name' => 'First', 'schedule' => '0 0 * * *', 'command' => 'start', 'devices' => ['d1']],
            ['name' => 'Second', 'schedule' => '0 1 * * *', 'command' => 'stop', 'devices' => ['d2']],
            ['name' => 'Third', 'schedule' => '0 2 * * *', 'command' => 'start', 'devices' => ['d3']],
        ];

        $provider = new ScheduleProvider($this->configuration, $schedulesData);
        $schedules = $provider->getScheduleList();

        $this->assertEquals('First', $schedules[0]->getName());
        $this->assertEquals('Second', $schedules[1]->getName());
        $this->assertEquals('Third', $schedules[2]->getName());
    }
}
