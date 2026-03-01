<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Runtime\Device;

use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Runtime\Device\Linux;
use phpseclib3\Net\SSH2;
use PHPUnit\Framework\TestCase;

class LinuxTest extends TestCase
{
    public function testStopDelegatesToSshPoweroffCommand(): void
    {
        $device = new class ($this->createConfiguration()) extends Linux {
            public ?string $lastCommand = null;

            protected function executeSshCommand(string $command): bool
            {
                $this->lastCommand = $command;

                return true;
            }
        };

        $device->configure(1, 'node-1', '10.0.0.10', '00:11:22:33:44:55', 'linux', null, null, null, null, 'root');
        $result = $device->stop();

        $this->assertTrue($result);
        $this->assertSame('systemctl poweroff', $device->lastCommand);
    }

    public function testStopReturnsFalseWhenSshCommandFails(): void
    {
        $device = new class ($this->createConfiguration()) extends Linux {
            protected function executeSshCommand(string $command): bool
            {
                return false;
            }
        };

        $device->configure(1, 'node-1', '10.0.0.10', '00:11:22:33:44:55', 'linux', null, null, null, null, 'root');

        $this->assertFalse($device->stop());
    }

    public function testExecuteSshCommandReturnsFalseWhenLoginFails(): void
    {
        $sshClient = $this->createMock(SSH2::class);
        $sshClient->expects($this->once())
            ->method('login')
            ->with('root', 'ssh-key')
            ->willReturn(false);
        $sshClient->expects($this->never())->method('exec');
        $sshClient->expects($this->never())->method('getExitStatus');

        $device = $this->createLinuxDeviceWithSshClient($sshClient);

        $this->assertFalse($device->callExecuteSshCommand('systemctl poweroff'));
    }

    public function testExecuteSshCommandReturnsTrueWhenExitStatusIsZero(): void
    {
        $sshClient = $this->createMock(SSH2::class);
        $sshClient->expects($this->once())
            ->method('login')
            ->with('root', 'ssh-key')
            ->willReturn(true);
        $sshClient->expects($this->once())
            ->method('exec')
            ->with('systemctl poweroff')
            ->willReturn('ok');
        $sshClient->expects($this->once())->method('getExitStatus')->willReturn(0);

        $device = $this->createLinuxDeviceWithSshClient($sshClient);

        $this->assertTrue($device->callExecuteSshCommand('systemctl poweroff'));
    }

    public function testExecuteSshCommandReturnsFalseWhenExitStatusIsNonZero(): void
    {
        $sshClient = $this->createMock(SSH2::class);
        $sshClient->expects($this->once())->method('login')->willReturn(true);
        $sshClient->expects($this->once())->method('exec')->willReturn('permission denied');
        $sshClient->expects($this->once())->method('getExitStatus')->willReturn(255);

        $device = $this->createLinuxDeviceWithSshClient($sshClient);

        $this->assertFalse($device->callExecuteSshCommand('systemctl poweroff'));
    }

    public function testExecuteSshCommandReturnsTrueWhenExitStatusIsNullAndOutputIsEmpty(): void
    {
        $sshClient = $this->createMock(SSH2::class);
        $sshClient->expects($this->once())->method('login')->willReturn(true);
        $sshClient->expects($this->once())->method('exec')->willReturn('   ');
        $sshClient->expects($this->once())->method('getExitStatus')->willReturn(null);

        $device = $this->createLinuxDeviceWithSshClient($sshClient);

        $this->assertTrue($device->callExecuteSshCommand('systemctl poweroff'));
    }

    public function testExecuteSshCommandReturnsFalseWhenExitStatusIsNullAndOutputIsNotEmpty(): void
    {
        $sshClient = $this->createMock(SSH2::class);
        $sshClient->expects($this->once())->method('login')->willReturn(true);
        $sshClient->expects($this->once())->method('exec')->willReturn('failed');
        $sshClient->expects($this->once())->method('getExitStatus')->willReturn(null);

        $device = $this->createLinuxDeviceWithSshClient($sshClient);

        $this->assertFalse($device->callExecuteSshCommand('systemctl poweroff'));
    }

    protected function createLinuxDeviceWithSshClient(SSH2 $sshClient): object
    {
        $device = new class ($this->createConfiguration(), $sshClient) extends Linux {
            public function __construct(Configuration $configuration, protected SSH2 $sshClient)
            {
                parent::__construct($configuration);
            }

            public function callExecuteSshCommand(string $command): bool
            {
                return $this->executeSshCommand($command);
            }

            protected function createSshClient(string $ip): SSH2
            {
                return $this->sshClient;
            }

            protected function loadSshKey(): mixed
            {
                return 'ssh-key';
            }
        };

        $device->configure(1, 'node-1', '10.0.0.10', '00:11:22:33:44:55', 'linux', null, null, null, null, 'root');

        return $device;
    }

    protected function createConfiguration(): Configuration
    {
        return new Configuration([
            'cron' => true,
            'ups_mode' => true,
            'ssh_key_path' => '/tmp/id_ed25519',
            'default_ssh_username' => 'root',
            'timezone' => 'UTC',
        ]);
    }
}
