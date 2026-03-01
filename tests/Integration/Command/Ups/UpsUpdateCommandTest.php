<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Ups;

use EvilStudio\HAT\Command\Ups\UpsUpdateCommand;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UpsUpdateCommandTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteUpdatesUps(): void
    {
        $upsService = $this->createMock(UpsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $currentUps = $this->createUpsEntity(1, 'Main UPS', 'ups-main', 'ups.local');
        $updatedUps = $this->createUpsEntity(1, 'Main UPS v2', 'ups-main-v2', 'ups2.local');

        $upsService->expects($this->once())->method('getUpsById')->with(1)->willReturn($currentUps);
        $upsService->expects($this->once())
            ->method('updateUps')
            ->with(1, 'Main UPS v2', 'ups-main-v2', 'ups2.local', 900)
            ->willReturn($updatedUps);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(ActionLog::SOURCE_CLI, 'ups.update', ActionLog::LEVEL_INFO, "UPS 'ups-main-v2' updated.");

        $tester = new CommandTester(new UpsUpdateCommand($upsService, $actionLogService));
        $exitCode = $tester->execute([
            'id' => '1',
            '--name' => 'Main UPS v2',
            '--identifier' => 'ups-main-v2',
            '--host' => 'ups2.local',
            '--safe-battery-runtime-threshold' => '900',
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("UPS 'Main UPS v2' updated.", $tester->getDisplay());
    }
}
