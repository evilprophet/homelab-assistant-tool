<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Schedule;

use EvilStudio\HAT\Command\Schedule\ScheduleCreateCommand;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ScheduleCreateCommandTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteCreatesSchedule(): void
    {
        $scheduleService = $this->createMock(ScheduleService::class);
        $deviceService = $this->createStub(DeviceService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $createdSchedule = $this->createScheduleEntity(5, 'Night Start', '0 2 * * *', ScheduleInterface::COMMAND_START);

        $scheduleService->expects($this->once())
            ->method('createSchedule')
            ->with('Night Start', '0 2 * * *', ScheduleInterface::COMMAND_START, [], true)
            ->willReturn($createdSchedule);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'schedule.create',
                ActionLog::LEVEL_INFO,
                "Schedule 'Night Start' created with ID 5."
            );

        $tester = new CommandTester(new ScheduleCreateCommand($scheduleService, $deviceService, $actionLogService));
        $exitCode = $tester->execute([
            'name' => 'Night Start',
            'cron-expression' => '0 2 * * *',
            'schedule-command' => ScheduleInterface::COMMAND_START,
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Schedule 'Night Start' created with ID 5.", $tester->getDisplay());
    }
}
