<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Contract;

use Symfony\Component\Console\Output\OutputInterface;

interface DeviceInterface
{
    public function configure(
        ?int $id,
        string $name,
        string $ip,
        string $mac,
        string $platform,
        ?int $upsId,
        ?string $upsName,
        ?string $upsIdentifier,
        ?string $username,
        ?int $upsLowBatteryRuntimeThreshold,
        bool $autoStopAllowed
    ): DeviceInterface;

    public function toArray(): array;

    public function getName(): string;

    public function getIp(): string;

    public function getMac(): string;

    public function getPlatform(): string;

    public function getUpsIdentifier(): ?string;

    public function getUsername(): ?string;

    public function getUpsLowBatteryRuntimeThreshold(): int;

    public function isAutoStopAllowed(): bool;

    public function getStatus(): ?bool;

    public function checkStatus(): void;

    public function start(): bool;

    public function stop(): bool;

    public function ssh(OutputInterface $output): int;
}
