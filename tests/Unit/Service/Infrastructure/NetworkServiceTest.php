<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Unit\Service\Infrastructure;

use EvilStudio\HAT\Service\Infrastructure\NetworkService;
use PHPUnit\Framework\TestCase;

class NetworkServiceTest extends TestCase
{
    public function testPingReturnsTrueWhenPingResultIsNotFalse(): void
    {
        $service = new NetworkService(
            pingFactory: static fn (): object => new class {
                public function ping(): string
                {
                    return 'pong';
                }
            }
        );

        $this->assertTrue($service->ping('10.0.0.10'));
    }

    public function testPingReturnsFalseWhenPingResultIsFalse(): void
    {
        $service = new NetworkService(
            pingFactory: static fn (): object => new class {
                public function ping(): bool
                {
                    return false;
                }
            }
        );

        $this->assertFalse($service->ping('10.0.0.10'));
    }

    public function testWakeOnLanReturnsTrueOnlyForOkResult(): void
    {
        $serviceOk = new NetworkService(
            wakeOnLanFactory: static fn (): object => new class {
                public function wake(array $macs): array
                {
                    return ['result' => 'OK'];
                }
            }
        );
        $serviceError = new NetworkService(
            wakeOnLanFactory: static fn (): object => new class {
                public function wake(array $macs): array
                {
                    return ['result' => 'ERROR'];
                }
            }
        );

        $this->assertTrue($serviceOk->wakeOnLan('00:11:22:33:44:55'));
        $this->assertFalse($serviceError->wakeOnLan('00:11:22:33:44:55'));
    }
}
