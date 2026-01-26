<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service;

use EvilStudio\HAT\Service\NetworkService;
use PHPUnit\Framework\TestCase;

class NetworkServiceTest extends TestCase
{
    public function testPingUsesFactory(): void
    {
        $fakePing = new class () {
            public function ping(): bool
            {
                return true;
            }
        };

        $service = new NetworkService(
            pingFactory: static fn(string $ip, int $ttl, int $timeout): object => $fakePing
        );

        $this->assertTrue($service->ping('192.168.1.1'));
    }

    public function testPingReturnsFalseWhenPingFails(): void
    {
        $fakePing = new class () {
            public function ping(): bool
            {
                return false;
            }
        };

        $service = new NetworkService(
            pingFactory: static fn(string $ip, int $ttl, int $timeout): object => $fakePing
        );

        $this->assertFalse($service->ping('192.168.1.2'));
    }

    public function testWakeOnLanUsesFactory(): void
    {
        $fakeWakeOnLan = new class () {
            public function wake(array $macAddresses): array
            {
                return ['result' => 'OK'];
            }
        };

        $service = new NetworkService(
            wakeOnLanFactory: static fn(): object => $fakeWakeOnLan
        );

        $this->assertTrue($service->wakeOnLan('00:11:22:33:44:55'));
    }
}
