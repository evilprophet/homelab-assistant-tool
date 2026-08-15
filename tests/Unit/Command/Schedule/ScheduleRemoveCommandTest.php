<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Schedule;

use EvilStudio\HAT\Command\Schedule\ScheduleRemoveCommand;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ScheduleRemoveCommandTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteRemovesScheduleWhenForced(): void
    {
        $scheduleService = $this->createMock(ScheduleService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $schedule = $this->createScheduleEntity(1, 'Night Start', '0 2 * * *', ScheduleInterface::COMMAND_START);

        $scheduleService->expects($this->once())->method('getScheduleById')->with(1)->willReturn($schedule);
        $scheduleService->expects($this->once())->method('removeSchedule')->with(1);
        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'schedule.remove',
                ActionLog::LEVEL_WARNING,
                "Schedule 'Night Start' removed. Detached devices: 0."
            );

        $tester = new CommandTester(new ScheduleRemoveCommand($scheduleService, $actionLogService));
        $exitCode = $tester->execute(['id' => '1', '--force' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Schedule 'Night Start' removed.", $tester->getDisplay());
    }

    public function testExecuteReportsRequiredArgumentWhenNonInteractiveWithoutId(): void
    {
        $scheduleService = $this->createMock(ScheduleService::class);
        $actionLogService = $this->createMock(ActionLogService::class);

        $scheduleService->expects($this->never())->method('listSchedules');
        $scheduleService->expects($this->never())->method('removeSchedule');

        $tester = new CommandTester(new ScheduleRemoveCommand($scheduleService, $actionLogService));
        $exitCode = $tester->execute(['--force' => true], ['interactive' => false]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString("Argument 'id' is required.", $tester->getDisplay());
    }
}
