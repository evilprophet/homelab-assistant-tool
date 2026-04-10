<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Factory;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Factory\RuntimeDeviceFactory;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Runtime\Device\Generic;
use EvilStudio\HAT\Runtime\Device\Linux;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;

class RuntimeDeviceFactoryTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testCreateFromEntityCreatesGenericRuntimeForGenericPlatform(): void
    {
        $configuration = $this->createConfiguration();
        $factory = new RuntimeDeviceFactory($configuration);
        $device = $this->createDeviceEntity(
            1,
            'node-1',
            '10.0.0.10',
            '00:11:22:33:44:55',
            DevicePlatform::GENERIC->value
        );
        $device->setUsername(null)->setUpsLowBatteryRuntimeThreshold(300);

        $runtimeDevice = $factory->createFromEntity($device);
        $runtimeData = $runtimeDevice->toArray();

        $this->assertInstanceOf(Generic::class, $runtimeDevice);
        $this->assertSame('node-1', $runtimeData['name']);
        $this->assertSame('10.0.0.10', $runtimeData['ip']);
        $this->assertSame('yes', $runtimeData['auto_stop']);
        $this->assertSame('5 min', $runtimeData['ups_low_battery_runtime_threshold']);
        $this->assertSame('root', $runtimeDevice->getUsername());
    }

    public function testCreateFromEntityCreatesLinuxRuntimeForLinuxBasedPlatform(): void
    {
        $configuration = $this->createConfiguration();
        $factory = new RuntimeDeviceFactory($configuration);
        $device = $this->createDeviceEntity(
            2,
            'node-2',
            '10.0.0.11',
            '00:11:22:33:44:66',
            DevicePlatform::UBUNTU->value
        );
        $device->setUsername('admin');

        $runtimeDevice = $factory->createFromEntity($device);

        $this->assertInstanceOf(Linux::class, $runtimeDevice);
        $this->assertSame('admin', $runtimeDevice->getUsername());
        $this->assertSame(DevicePlatform::UBUNTU->value, $runtimeDevice->getPlatform());
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
