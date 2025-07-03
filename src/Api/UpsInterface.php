<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Api;

interface UpsInterface
{
    public function updateStatus(): void;

    public function toArray(): array;

    public function getName(): string;

    public function getModelName(): ?string;

    public function getSerialNumber(): ?string;

    public function getIdentifier(): string;

    public function getHost(): string;

    public function getStatus(): ?string;

    public function getPower(): ?int;

    public function getRealPower(): ?int;

    public function getBatteryLevel(): ?int;

    public function getBatteryRuntime(): ?int;

    public function getLowBatteryRuntimeThreshold(): ?int;

    public function getSafeBatteryRuntimeThreshold(): ?int;

    public function isOnBattery(): ?bool;

    public function isBatteryRuntimeLow(): ?bool;
}
