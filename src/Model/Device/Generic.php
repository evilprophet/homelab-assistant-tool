<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Model\Device;

use Diegonz\PHPWakeOnLan\PHPWakeOnLan;
use EvilStudio\HAT\Api\DeviceInterface;
use EvilStudio\HAT\Exception\Platform\NoSupportedAction;
use EvilStudio\HAT\Helper\Configuration;
use Exception;
use JJG\Ping;
use Symfony\Component\Console\Output\OutputInterface;

class Generic implements DeviceInterface
{
    public function __construct(
        protected Configuration $configuration
    )
    {
    }

    protected string $name;
    protected string $platform;
    protected string $ip;
    protected string $mac;
    protected ?string $username;
    protected ?bool $status = null;

    public function configure(string $name, string $platform, string $ip, string $mac, ?string $username): DeviceInterface
    {
        $this->name = $name;
        $this->platform = $platform;
        $this->ip = $ip;
        $this->mac = $mac;
        $this->username = $username;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getMac(): string
    {
        return $this->mac;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getStatus(bool $asString = false): ?bool
    {
        return $this->status;
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->getName(),
            'platform' => $this->getPlatform(),
            'ip' => $this->getIp(),
            'mac' => $this->getMac(),

        ];

        if ($this->getStatus() !== null) {
            $data['status'] = $this->getStatus() ? 'online' : 'offline';
        }

        return $data;
    }

    public function checkStatus(): void
    {
        try {
            $ip = $this->getIp();
            $ping = new Ping($ip, 32, 1);

            $this->status = $ping->ping() !== false;
        } catch (Exception) {
            $this->status = false;
        }
    }

    public function start(): bool
    {
        try {
            $macAddresses = [$this->getMac()];

            $wakeOnLan = new PHPWakeOnLan();
            $result = $wakeOnLan->wake($macAddresses);
        } catch (Exception) {
            return false;
        }

        return $result['result'] == 'OK';
    }

    public function stop(): bool
    {
        throw new NoSupportedAction('Stop action is not supported on Generic device.');
    }

    public function ssh(OutputInterface $output): void
    {
        throw new NoSupportedAction('SSH action is not supported on Generic device.');
    }
}
