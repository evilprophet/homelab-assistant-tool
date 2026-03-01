<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Contract;

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
    }

    public function testUsesLinuxRuntimeReturnsTrueOnlyForLinuxBasedPlatforms(): void
    {
        $this->assertTrue(DevicePlatform::LINUX->usesLinuxRuntime());
        $this->assertTrue(DevicePlatform::UBUNTU->usesLinuxRuntime());
        $this->assertTrue(DevicePlatform::PROXMOX_DM->usesLinuxRuntime());

        $this->assertFalse(DevicePlatform::GENERIC->usesLinuxRuntime());
        $this->assertFalse(DevicePlatform::SYNOLOGY_DSM->usesLinuxRuntime());
    }
}
