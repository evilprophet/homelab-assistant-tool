<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Command\Ups;

use EvilStudio\HAT\Command\Ups\UpsCreateCommand;
use EvilStudio\HAT\Entity\ActionLog;
use EvilStudio\HAT\Service\Application\ActionLogService;
use EvilStudio\HAT\Service\Application\UpsService;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UpsCreateCommandTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testExecuteCreatesUps(): void
    {
        $upsService = $this->createMock(UpsService::class);
        $actionLogService = $this->createMock(ActionLogService::class);
        $createdUps = $this->createUpsEntity(10, 'Main UPS', 'ups-main', 'ups.local');

        $upsService->expects($this->once())
            ->method('createUps')
            ->with('Main UPS', 'ups-main', 'ups.local', 600)
            ->willReturn($createdUps);

        $actionLogService->expects($this->once())
            ->method('createActionLog')
            ->with(ActionLog::SOURCE_CLI, 'ups.create', ActionLog::LEVEL_INFO, "UPS 'ups-main' created with ID 10.");

        $tester = new CommandTester(new UpsCreateCommand($upsService, $actionLogService));
        $exitCode = $tester->execute([
            'name' => 'Main UPS',
            'identifier' => 'ups-main',
            'host' => 'ups.local',
            '--safe-battery-runtime-threshold' => '600',
        ], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("UPS 'Main UPS' created with ID 10.", $tester->getDisplay());
    }
}
