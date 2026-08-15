<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Entity;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Entity\Schedule;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EnumColumnInvariantTest extends TestCase
{
    public function testDeviceRejectsUnsupportedPlatform(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported platform 'toaster_os'.");

        (new Device())->setPlatform('toaster_os');
    }

    public function testDeviceAcceptsEveryDeclaredPlatform(): void
    {
        foreach (DevicePlatform::values() as $platform) {
            $this->assertSame($platform, (new Device())->setPlatform($platform)->getPlatform());
        }
    }

    public function testScheduleRejectsUnsupportedCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid schedule command 'restart'.");

        (new Schedule())->setCommand('restart');
    }

    public function testScheduleAcceptsEveryDeclaredCommand(): void
    {
        foreach (ScheduleInterface::COMMANDS as $command) {
            $this->assertSame($command, (new Schedule())->setCommand($command)->getCommand());
        }
    }

    public function testActionLogRejectsUnknownSource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid action log source 'API'.");

        (new ActionLog())->setSource('API');
    }

    public function testActionLogRejectsUnknownLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid action log level 'critical'.");

        (new ActionLog())->setLevel('critical');
    }

    public function testActionLogAcceptsEveryDeclaredSourceAndLevel(): void
    {
        foreach (ActionLog::SOURCES as $source) {
            $this->assertSame($source, (new ActionLog())->setSource($source)->getSource());
        }

        foreach (ActionLog::LEVELS as $level) {
            $this->assertSame($level, (new ActionLog())->setLevel($level)->getLevel());
        }
    }
}
