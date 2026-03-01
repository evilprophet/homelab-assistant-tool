<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Command\Device;

use EvilStudio\HAT\Command\Device\DeviceListCommand;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Service\Runtime\DeviceOperationsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DeviceListCommandTest extends TestCase
{
    public function testExecuteShowsDevicesTable(): void
    {
        $runtimeService = $this->createMock(DeviceOperationsService::class);
        $runtimeDevice = $this->createMock(DeviceInterface::class);

        $runtimeService->expects($this->once())->method('listDevices')->with(false)->willReturn([$runtimeDevice]);
        $runtimeDevice->expects($this->once())->method('toArray')->willReturn([
            'id' => 1,
            'name' => 'node-1',
            'ip' => '10.0.0.10',
            'mac' => '00:11:22:33:44:55',
            'platform' => 'generic',
            'platform_key' => 'generic',
            'ups' => '2:Main UPS',
            'ups_low_battery_runtime_threshold' => '5 min',
        ]);

        $tester = new CommandTester(new DeviceListCommand($runtimeService));
        $exitCode = $tester->execute([], ['interactive' => false]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('node-1', $tester->getDisplay());
        $this->assertStringContainsString('2:Main UPS', $tester->getDisplay());
        $this->assertStringContainsString('5 min', $tester->getDisplay());
    }
}
