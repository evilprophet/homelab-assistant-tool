<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Api;

use Symfony\Component\Console\Output\OutputInterface;

interface DeviceInterface
{
    public function configure(string $name, string $platform, string $ip, string $mac): DeviceInterface;

    public function getName(): string;

    public function getPlatform(): string;

    public function getIp(): string;

    public function getMac(): string;

    public function getStatus(bool $asString): ?bool;

    public function toArray(): array;

    public function checkStatus(): void;

    public function start(): bool;

    public function stop(): bool;

    public function ssh(OutputInterface $output): void;
}
