<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Contract;

use EvilStudio\HAT\Contract\DeviceAction;
use EvilStudio\HAT\Contract\DevicePlatform;
use PHPUnit\Framework\TestCase;

class DevicePlatformTest extends TestCase
{
    public function testValuesReturnsAllEnumValues(): void
    {
        $values = DevicePlatform::values();

        $this->assertContains(DevicePlatform::GENERIC->value, $values);
        $this->assertContains(DevicePlatform::LINUX->value, $values);
        $this->assertContains(DevicePlatform::PROXMOX_VE->value, $values);
        $this->assertContains(DevicePlatform::ASUSTOR_ADM->value, $values);
        $this->assertContains(DevicePlatform::QNAP_QTS->value, $values);
    }

    public function testUsesLinuxRuntimeReturnsTrueOnlyForLinuxBasedPlatforms(): void
    {
        $this->assertTrue(DevicePlatform::LINUX->usesLinuxRuntime());
        $this->assertTrue(DevicePlatform::UBUNTU->usesLinuxRuntime());
        $this->assertTrue(DevicePlatform::PROXMOX_DM->usesLinuxRuntime());

        $this->assertFalse(DevicePlatform::GENERIC->usesLinuxRuntime());
        $this->assertFalse(DevicePlatform::SYNOLOGY_DSM->usesLinuxRuntime());
    }

    public function testSupportsActionReflectsPlatformCapabilities(): void
    {
        $this->assertTrue(DevicePlatform::LINUX->supportsAction(DeviceAction::START));
        $this->assertTrue(DevicePlatform::LINUX->supportsAction(DeviceAction::STOP));
        $this->assertTrue(DevicePlatform::LINUX->supportsAction(DeviceAction::SSH));

        $this->assertTrue(DevicePlatform::SYNOLOGY_DSM->supportsAction(DeviceAction::START));
        $this->assertFalse(DevicePlatform::SYNOLOGY_DSM->supportsAction(DeviceAction::STOP));
        $this->assertFalse(DevicePlatform::SYNOLOGY_DSM->supportsAction(DeviceAction::SSH));

        $this->assertFalse(DevicePlatform::ASUSTOR_ADM->supportsAction(DeviceAction::STOP));
        $this->assertFalse(DevicePlatform::QNAP_QTS->supportsAction(DeviceAction::SSH));
    }
}
