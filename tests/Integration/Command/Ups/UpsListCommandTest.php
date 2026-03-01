<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Ups;

use EvilStudio\HAT\Contract\UpsInterface;
use EvilStudio\HAT\Command\Ups\UpsListCommand;
use EvilStudio\HAT\Service\Runtime\UpsRuntimeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UpsListCommandTest extends TestCase
{
    public function testExecuteShowsUpsTable(): void
    {
        $upsRuntimeService = $this->createMock(UpsRuntimeService::class);
        $runtimeUps = $this->createMock(UpsInterface::class);
        $upsRuntimeService->expects($this->once())->method('listRuntimeUps')->willReturn(['ups-main' => $runtimeUps]);
        $runtimeUps->expects($this->once())->method('updateStatus');
        $runtimeUps->expects($this->once())->method('toArray')->willReturn([
            'id' => 1,
            'name' => 'Main UPS',
            'identifier' => 'ups-main',
            'model_name' => 'Eaton 5P',
            'serial_number' => 'GM123',
            'status' => 'Online',
            'power' => 'P: 63 W',
            'battery' => 'Current: 99%',
            'linked_device' => '10:node-1',
        ]);

        $tester = new CommandTester(new UpsListCommand($upsRuntimeService));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('1', $tester->getDisplay());
        $this->assertStringContainsString('Main UPS', $tester->getDisplay());
        $this->assertStringContainsString('ups-main', $tester->getDisplay());
        $this->assertStringContainsString('Online', $tester->getDisplay());
        $this->assertStringContainsString('10:node-1', $tester->getDisplay());
    }
}
