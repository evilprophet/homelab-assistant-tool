<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Ups;

use EvilStudio\HAT\Command\Ups\UpsRemoveCommand;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UpsRemoveCommandTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteRemovesUpsWhenForced(): void
    {
        $upsService = $this->createMock(UpsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $ups = $this->createUpsEntity(1, 'Main UPS', 'ups-main', 'ups.local');

        $upsService->expects($this->once())->method('getUpsById')->with(1)->willReturn($ups);
        $upsService->expects($this->once())->method('removeUps')->with(1);
        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(
                ActionLog::SOURCE_CLI,
                'ups.remove',
                ActionLog::LEVEL_WARNING,
                "UPS 'ups-main' removed. Detached devices: 0."
            );

        $tester = new CommandTester(new UpsRemoveCommand($upsService, $actionLogService));
        $exitCode = $tester->execute(['id' => '1', '--force' => true], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("UPS 'ups-main' removed.", $tester->getDisplay());
    }
}
