<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Schedule;

use EvilStudio\HAT\Command\Schedule\ScheduleListCommand;
use EvilStudio\HAT\Runtime\Schedule as RuntimeSchedule;
use EvilStudio\HAT\Service\Runtime\ScheduleRuntimeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ScheduleListCommandTest extends TestCase
{
    public function testExecuteShowsSchedulesTable(): void
    {
        $scheduleRuntimeService = $this->createMock(ScheduleRuntimeService::class);
        $runtimeSchedule = new RuntimeSchedule(
            1,
            'Night Start',
            true,
            '0 2 * * *',
            'start',
            [['id' => 10, 'name' => 'node-1']]
        );

        $scheduleRuntimeService
            ->expects($this->once())
            ->method('listRuntimeSchedules')
            ->willReturn([$runtimeSchedule]);

        $tester = new CommandTester(new ScheduleListCommand($scheduleRuntimeService));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Night Start', $tester->getDisplay());
        $this->assertStringContainsString('node-1', $tester->getDisplay());
    }
}
