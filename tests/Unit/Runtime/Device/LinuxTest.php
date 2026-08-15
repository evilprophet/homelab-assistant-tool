<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Runtime\Device;

use EvilStudio\HAT\Exception\SshCommandFailed;
use EvilStudio\HAT\Exception\SshKeyNotReadable;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Runtime\Device\Linux;
use EvilStudio\HAT\Tests\Support\TemporaryPathTrait;
use phpseclib3\Crypt\Common\PrivateKey;
use phpseclib3\Crypt\EC;
use phpseclib3\Exception\NoKeyLoadedException;
use phpseclib3\Net\SSH2;
use PHPUnit\Framework\TestCase;

class LinuxTest extends TestCase
{
    use TemporaryPathTrait;

    protected function tearDown(): void
    {
        $this->removeTemporaryPaths();

        parent::tearDown();
    }

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

        $device->configure(
            1,
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            'linux',
            null,
            null,
            null,
            'root',
            null,
            true
        );
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

        $device->configure(
            1,
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            'linux',
            null,
            null,
            null,
            'root',
            null,
            true
        );

        $this->assertFalse($device->stop());
    }

    public function testExecuteSshCommandThrowsWhenLoginFails(): void
    {
        $sshClient = $this->createMock(SSH2::class);
        $sshClient->expects($this->once())
            ->method('login')
            ->with('root', 'ssh-key')
            ->willReturn(false);
        $sshClient->expects($this->never())->method('exec');
        $sshClient->expects($this->never())->method('getExitStatus');

        $device = $this->createLinuxDeviceWithSshClient($sshClient);

        $this->expectException(SshCommandFailed::class);
        $this->expectExceptionMessage("SSH login as 'root' to device 'node-1' failed.");

        $device->callExecuteSshCommand('systemctl poweroff');
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

    public function testExecuteSshCommandThrowsWithRemoteOutputWhenExitStatusIsNonZero(): void
    {
        $sshClient = $this->createMock(SSH2::class);
        $sshClient->expects($this->once())->method('login')->willReturn(true);
        $sshClient->expects($this->once())->method('exec')->willReturn('permission denied');
        $sshClient->expects($this->once())->method('getExitStatus')->willReturn(255);

        $device = $this->createLinuxDeviceWithSshClient($sshClient);

        $this->expectException(SshCommandFailed::class);
        $this->expectExceptionMessage(
            "Command 'systemctl poweroff' on device 'node-1' exited with status 255: permission denied"
        );

        $device->callExecuteSshCommand('systemctl poweroff');
    }

    public function testExecuteSshCommandReturnsTrueWhenExitStatusIsMissingAndOutputIsEmpty(): void
    {
        $sshClient = $this->createMock(SSH2::class);
        $sshClient->expects($this->once())->method('login')->willReturn(true);
        $sshClient->expects($this->once())->method('exec')->willReturn('   ');
        $sshClient->expects($this->once())->method('getExitStatus')->willReturn(false);

        $device = $this->createLinuxDeviceWithSshClient($sshClient);

        $this->assertTrue($device->callExecuteSshCommand('systemctl poweroff'));
    }

    public function testExecuteSshCommandReturnsFalseWhenExitStatusIsMissingAndOutputIsNotEmpty(): void
    {
        $sshClient = $this->createMock(SSH2::class);
        $sshClient->expects($this->once())->method('login')->willReturn(true);
        $sshClient->expects($this->once())->method('exec')->willReturn('failed');
        $sshClient->expects($this->once())->method('getExitStatus')->willReturn(false);

        $device = $this->createLinuxDeviceWithSshClient($sshClient);

        $this->assertFalse($device->callExecuteSshCommand('systemctl poweroff'));
    }

    public function testSshPassesLoginAsAnOptionAndTerminatesOptionParsing(): void
    {
        $device = new class ($this->createConfiguration()) extends Linux {
            public function callBuildSshArguments(): array
            {
                return $this->buildSshArguments();
            }
        };

        $device->configure(
            1,
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            'linux',
            null,
            null,
            null,
            'admin',
            null,
            true
        );

        $arguments = $device->callBuildSshArguments();

        // A single 'admin@10.0.0.10' token would let a dash-leading login reach
        // ssh as an option; '-l' plus '--' makes that impossible.
        $this->assertNotContains('admin@10.0.0.10', $arguments);
        $this->assertSame(['-l', 'admin', '--', '10.0.0.10'], array_slice($arguments, -4));
    }

    public function testLoadSshKeyReadsAnUnencryptedKey(): void
    {
        $keyPath = $this->createTemporaryPath('hat-ssh-key-');
        file_put_contents($keyPath, $this->generatePrivateKey());

        $device = $this->createKeyLoadingDevice($this->createConfiguration($keyPath));

        $this->assertInstanceOf(PrivateKey::class, $device->callLoadSshKey());
    }

    public function testLoadSshKeyReadsAnEncryptedKeyWithTheConfiguredPassphrase(): void
    {
        $keyPath = $this->createTemporaryPath('hat-ssh-key-');
        file_put_contents($keyPath, $this->generatePrivateKey('s3cret'));

        $device = $this->createKeyLoadingDevice($this->createConfiguration($keyPath, 's3cret'));

        $this->assertInstanceOf(PrivateKey::class, $device->callLoadSshKey());
    }

    public function testLoadSshKeyFailsForAnEncryptedKeyWithoutAPassphrase(): void
    {
        $keyPath = $this->createTemporaryPath('hat-ssh-key-');
        file_put_contents($keyPath, $this->generatePrivateKey('s3cret'));

        $device = $this->createKeyLoadingDevice($this->createConfiguration($keyPath));

        // Pins the exception type: Cron catches Exception, so a key error must not
        // escape as an Error and kill the whole run.
        $this->expectException(NoKeyLoadedException::class);

        $device->callLoadSshKey();
    }

    public function testLoadSshKeyFailsForAMalformedKeyFile(): void
    {
        $keyPath = $this->createTemporaryPath('hat-ssh-key-');
        file_put_contents($keyPath, 'this is not a private key');

        $device = $this->createKeyLoadingDevice($this->createConfiguration($keyPath));

        $this->expectException(NoKeyLoadedException::class);

        $device->callLoadSshKey();
    }

    public function testLoadSshKeyFailsWithADescriptiveErrorWhenTheFileIsMissing(): void
    {
        $missingKeyPath = $this->createTemporaryPath('hat-ssh-missing-');

        $device = $this->createKeyLoadingDevice($this->createConfiguration($missingKeyPath));

        $this->expectException(SshKeyNotReadable::class);
        $this->expectExceptionMessage('is missing or not readable');

        $device->callLoadSshKey();
    }

    protected function createKeyLoadingDevice(Configuration $configuration): object
    {
        return new class ($configuration) extends Linux {
            public function callLoadSshKey(): mixed
            {
                return $this->loadSshKey();
            }
        };
    }

    protected function generatePrivateKey(?string $passphrase = null): string
    {
        $key = EC::createKey('Ed25519');

        return $passphrase === null ? (string)$key : (string)$key->withPassword($passphrase);
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

        $device->configure(
            1,
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            'linux',
            null,
            null,
            null,
            'root',
            null,
            true
        );

        return $device;
    }

    protected function createConfiguration(
        string $sshKeyPath = '/tmp/id_ed25519',
        ?string $passphrase = null
    ): Configuration {
        return new Configuration([
            'cron' => true,
            'ups_mode' => true,
            'ssh_key_path' => $sshKeyPath,
            'ssh_key_passphrase' => $passphrase ?? '',
            'default_ssh_username' => 'root',
            'timezone' => 'UTC',
        ]);
    }
}
