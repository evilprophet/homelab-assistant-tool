<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Runtime\Device;

use EvilStudio\HAT\Contract\DevicePlatform;
use EvilStudio\HAT\Contract\DeviceInterface;
use EvilStudio\HAT\Exception\UnsupportedDeviceAction;
use EvilStudio\HAT\Helper\Configuration;
use EvilStudio\HAT\Service\Infrastructure\NetworkService;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;

class Generic implements DeviceInterface
{
    public function __construct(
        protected Configuration $configuration,
        protected ?NetworkService $networkService = null
    ) {
        $this->networkService ??= new NetworkService();
    }

    protected string $name;
    protected ?int $id;
    protected string $ip;
    protected string $mac;
    protected string $platform;
    protected ?int $upsId;
    protected ?string $upsName;
    protected ?string $upsIdentifier;
    protected int $upsLowBatteryRuntimeThreshold;
    protected string $username;
    protected ?bool $status = null;

    public function configure(
        ?int $id,
        string $name,
        string $ip,
        string $mac,
        string $platform,
        ?int $upsId,
        ?string $upsName,
        ?string $upsIdentifier,
        ?int $upsLowBatteryRuntimeThreshold,
        ?string $username
    ): DeviceInterface {
        $this->id = $id;
        $this->name = $name;
        $this->ip = $ip;
        $this->mac = $mac;
        $this->platform = $platform;
        $this->upsId = $upsId;
        $this->upsName = $upsName;
        $this->upsIdentifier = $upsIdentifier;
        $this->upsLowBatteryRuntimeThreshold = (int)$upsLowBatteryRuntimeThreshold;
        $this->username = $username ?? $this->configuration->getDefaultSshUsername();

        return $this;
    }

    public function toArray(): array
    {
        $platform = DevicePlatform::tryFrom($this->getPlatform());
        $platformKey = $platform?->value ?? $this->getPlatform();
        $platformLabel = $platform?->label() ?? $this->getPlatform();

        if ($this->getUpsLowBatteryRuntimeThreshold()) {
            $upsLowBatteryRuntimeThreshold = sprintf('%s min', round($this->getUpsLowBatteryRuntimeThreshold() / 60));
        } else {
            $upsLowBatteryRuntimeThreshold = '-';
        }

        $upsLink = '-';
        if ($this->upsId !== null && $this->upsName !== null) {
            $upsLink = sprintf('%d:%s', $this->upsId, $this->upsName);
        }

        $data = [
            'id' => $this->id ?? '-',
            'name' => $this->getName(),
            'ip' => $this->getIp(),
            'mac' => $this->getMac(),
            'platform' => $platformLabel,
            'platform_key' => $platformKey,
            'ups' => $upsLink,
            'ups_low_battery_runtime_threshold' => $upsLowBatteryRuntimeThreshold
        ];

        if ($this->getStatus() !== null) {
            $data['status'] = $this->getStatus() ? 'online' : 'offline';
        }

        return $data;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getMac(): string
    {
        return $this->mac;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getUpsIdentifier(): ?string
    {
        return $this->upsIdentifier;
    }

    public function getUpsLowBatteryRuntimeThreshold(): int
    {
        return $this->upsLowBatteryRuntimeThreshold;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getStatus(): ?bool
    {
        return $this->status;
    }

    public function checkStatus(): void
    {
        try {
            $this->status = $this->networkService->ping($this->getIp());
        } catch (Exception) {
            $this->status = false;
        }
    }

    public function start(): bool
    {
        try {
            $result = $this->networkService->wakeOnLan($this->getMac());
        } catch (Exception) {
            return false;
        }

        return $result;
    }

    public function stop(): bool
    {
        throw new UnsupportedDeviceAction(
            sprintf("Stop action is not supported on '%s' device.", $this->getPlatform())
        );
    }

    public function ssh(OutputInterface $output): void
    {
        throw new UnsupportedDeviceAction(
            sprintf("SSH action is not supported on '%s' device.", $this->getPlatform())
        );
    }
}
