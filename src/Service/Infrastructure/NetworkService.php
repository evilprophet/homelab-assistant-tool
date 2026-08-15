<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Infrastructure;

use Closure;
use Diegonz\PHPWakeOnLan\PHPWakeOnLan;
use JJG\Ping;

class NetworkService
{
    protected const int PING_ATTEMPTS = 3;

    public function __construct(
        ?callable $pingFactory = null,
        ?callable $wakeOnLanFactory = null
    ) {
        $this->pingFactory = $pingFactory ? Closure::fromCallable($pingFactory) : null;
        $this->wakeOnLanFactory = $wakeOnLanFactory ? Closure::fromCallable($wakeOnLanFactory) : null;
    }

    public function ping(string $ip, int $ttl = 32, int $timeout = 1): bool
    {
        $factory = $this->pingFactory ??
            static fn(string $ip, int $ttl, int $timeout): Ping => new Ping($ip, $ttl, $timeout);

        // JJG\Ping hardcodes a single ICMP packet, so one dropped packet would report a
        // running device as offline and skip its battery-mode shutdown. Retrying only
        // after a failure keeps reachable devices at one packet.
        for ($attempt = 1; $attempt <= self::PING_ATTEMPTS; $attempt++) {
            if ($factory($ip, $ttl, $timeout)->ping() !== false) {
                return true;
            }
        }

        return false;
    }

    public function wakeOnLan(string $mac): bool
    {
        $factory = $this->wakeOnLanFactory ?? static fn(): PHPWakeOnLan => new PHPWakeOnLan();
        $wakeOnLan = $factory();
        $result = $wakeOnLan->wake([$mac]);

        return ($result['result'] ?? null) === 'OK';
    }

    protected ?Closure $pingFactory;
    protected ?Closure $wakeOnLanFactory;
}
