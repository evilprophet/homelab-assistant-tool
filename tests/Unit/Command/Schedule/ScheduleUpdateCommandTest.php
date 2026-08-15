<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Schedule;

use EvilStudio\HAT\Command\Schedule\ScheduleUpdateCommand;
use EvilStudio\HAT\Contract\ScheduleInterface;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\DeviceService;
use EvilStudio\HAT\Service\Application\ScheduleService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ScheduleUpdateCommandTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteUpdatesSchedule(): void
    {
        $scheduleService = $this->createMock(ScheduleService::class);
        $deviceService = $this->createStub(DeviceService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $currentSchedule = $this->createScheduleEntity(1, 'Night Start', '0 2 * * *', ScheduleInterface::COMMAND_START);
        $oldDevice = $this->createDeviceEntity(1, 'node-old');
        $currentSchedule->addDevice($oldDevice);
        $updatedSchedule = $this->createScheduleEntity(1, 'Night Stop', '30 4 * * *', ScheduleInterface::COMMAND_STOP);

        $scheduleService->expects($this->once())->method('getScheduleById')->with(1)->willReturn($currentSchedule);
        $scheduleService->expects($this->once())
            ->method('updateSchedule')
            ->with(1, 'Night Stop', false, '30 4 * * *', ScheduleInterface::COMMAND_STOP, [2])
            ->willReturn($updatedSchedule);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(ActionLog::SOURCE_CLI, 'schedule.update', ActionLog::LEVEL_INFO, "Schedule 'Night Stop' updated.");

        $tester = new CommandTester(new ScheduleUpdateCommand($scheduleService, $deviceService, $actionLogService));
        $exitCode = $tester->execute([
            'id' => '1',
            '--name' => 'Night Stop',
            '--is-enabled' => '0',
            '--cron-expression' => '30 4 * * *',
            '--command' => ScheduleInterface::COMMAND_STOP,
            '--device-id' => ['2'],
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Schedule 'Night Stop' updated.", $tester->getDisplay());
    }
}
