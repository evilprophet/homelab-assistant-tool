<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Api;

interface ContainerInterface
{
    public function configure(int $id, string $name, string $ip, string $deviceIdentifier): ContainerInterface;

    public function toArray(): array;

    public function getId(): int;

    public function getName(): string;

    public function getIp(): string;

    public function getDeviceIdentifier(): ?string;

    public function getStatus(): ?bool;

    public function checkStatus(): void;

    public function start(): bool;

    public function stop(): bool;

    public function restart(): bool;
}
