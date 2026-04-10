<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Runtime\Device;

use EvilStudio\HAT\Exception\UnsupportedDeviceAction;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Runtime\Device\Generic;
use EvilStudio\HAT\Service\Infrastructure\NetworkService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class GenericTest extends TestCase
{
    public function testConfigureUsesDefaultUsernameAndFormatsArrayData(): void
    {
        $networkService = $this->createMock(NetworkService::class);
        $device = new Generic($this->createConfiguration('root'), $networkService);
        $device->configure(
            1,
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            'generic',
            2,
            'Main UPS',
            'ups-main',
            null,
            600,
            true
        );
        $networkService->expects($this->once())->method('ping')->with('10.0.0.10')->willReturn(true);

        $device->checkStatus();
        $data = $device->toArray();

        $this->assertSame('root', $device->getUsername());
        $this->assertSame('online', $data['status']);
        $this->assertSame('yes', $data['auto_stop']);
        $this->assertSame('10 min', $data['ups_low_battery_runtime_threshold']);
    }

    public function testConfigureUsesDefaultUsernameWhenProvidedUsernameIsEmptyString(): void
    {
        $device = new Generic($this->createConfiguration('root'), $this->createMock(NetworkService::class));
        $device->configure(
            1,
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            'generic',
            null,
            null,
            null,
            '',
            null,
            true
        );

        $this->assertSame('root', $device->getUsername());
    }

    public function testStartReturnsFalseWhenWakeOnLanThrowsException(): void
    {
        $networkService = $this->createMock(NetworkService::class);
        $networkService->expects($this->once())
            ->method('wakeOnLan')
            ->with('00:11:22:33:44:55')
            ->willThrowException(new \RuntimeException('WOL failed.'));

        $device = new Generic($this->createConfiguration('root'), $networkService);
        $device->configure(
            1,
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            'generic',
            null,
            null,
            null,
            'admin',
            null,
            true
        );

        $this->assertFalse($device->start());
    }

    public function testStopAndSshThrowUnsupportedAction(): void
    {
        $device = new Generic($this->createConfiguration('root'), $this->createMock(NetworkService::class));
        $device->configure(1, 'node-1', '10.0.0.10', '00:11:22:33:44:55', 'generic', null, null, null, 'admin', null, true);

        $this->expectException(UnsupportedDeviceAction::class);
        $this->expectExceptionMessage("Stop action is not supported on 'generic' device.");
        $device->stop();
    }

    public function testSshThrowsUnsupportedAction(): void
    {
        $device = new Generic($this->createConfiguration('root'), $this->createMock(NetworkService::class));
        $device->configure(1, 'node-1', '10.0.0.10', '00:11:22:33:44:55', 'generic', null, null, null, 'admin', null, true);

        $this->expectException(UnsupportedDeviceAction::class);
        $this->expectExceptionMessage("SSH action is not supported on 'generic' device.");
        $device->ssh($this->createMock(OutputInterface::class));
    }

    protected function createConfiguration(string $defaultUser): Configuration
    {
        return new Configuration([
            'cron' => true,
            'ups_mode' => true,
            'ssh_key_path' => '/tmp/id_ed25519',
            'default_ssh_username' => $defaultUser,
            'timezone' => 'UTC',
        ]);
    }
}
