<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Factory;

use EvilStudio\HAT\Factory\RuntimeUpsFactory;
use EvilStudio\HAT\Runtime\Ups as RuntimeUps;
use EvilStudio\HAT\Tests\Support\EntityTestHelperTrait;
use PHPUnit\Framework\TestCase;

class RuntimeUpsFactoryTest extends TestCase
{
    use EntityTestHelperTrait;

    public function testCreateFromEntityCreatesRuntimeUpsWithExpectedValues(): void
    {
        $factory = new RuntimeUpsFactory();
        $ups = $this->createUpsEntity(1, 'Main UPS', 'ups-main', 'ups.local')->setSafeBatteryRuntimeThreshold(900);

        $runtimeUps = $factory->createFromEntity($ups);

        $this->assertInstanceOf(RuntimeUps::class, $runtimeUps);
        $this->assertSame('Main UPS', $runtimeUps->getName());
        $this->assertSame('ups-main', $runtimeUps->getIdentifier());
        $this->assertSame('ups.local', $runtimeUps->getHost());
        $this->assertSame(900, $runtimeUps->getSafeBatteryRuntimeThreshold());
    }
}
