<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Model;

use EvilStudio\HAT\Api\UpsInterface;
use EvilStudio\HAT\Exception\UpsFailedUpdateStatus;

class Ups implements UpsInterface
{
    protected const string COMMAND_UPDATE_STATUS = 'upsc %s@%s';

    protected const string PROPERTY_DEVICE_MODEL = 'device.model';
    protected const string PROPERTY_DEVICE_SERIAL = 'device.serial';
    protected const string PROPERTY_UPS_STATUS = 'ups.status';
    protected const string PROPERTY_UPS_POWER = 'ups.power';
    protected const string PROPERTY_UPS_REAL_POWER = 'ups.realpower';
    protected const string PROPERTY_BATTERY_RUNTIME = 'battery.runtime';
    protected const string PROPERTY_BATTERY_RUNTIME_LOW = 'battery.runtime.low';
    protected const string PROPERTY_BATTERY_CHARGE = 'battery.charge';
    protected const string PROPERTY_VALUE_UPS_STATUS_ON_BATTERY = 'OB';

    protected array $properties = [];
    protected ?string $modelName = null;
    protected ?string $serialNumber = null;
    protected ?string $status = null;
    protected ?int $power = null;
    protected ?int $realPower = null;
    protected ?int $batteryLevel = null;
    protected ?int $batteryRuntime = null;
    protected ?int $batteryRuntimeLow = null;

    protected ?bool $isBatteryRuntimeLow = null;
    protected ?bool $isOnBattery = null;

    public function __construct(
        protected string $name,
        protected string $identifier,
        protected string $host
    ) {
    }

    public function updateStatus(): void
    {
        $command = sprintf(self::COMMAND_UPDATE_STATUS, $this->getIdentifier(), $this->getHost());
        exec($command, $output, $resultCode);

        if ($resultCode !== 0) {
            throw new UpsFailedUpdateStatus(sprintf("Cannot load status for UPS '%s' at '%s'", $this->getIdentifier(), $this->getHost()));
        }

        foreach ($output as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $this->properties[trim($key)] = trim($value);
        }

        $this->modelName = $this->properties[self::PROPERTY_DEVICE_MODEL] ?? '';
        $this->serialNumber = $this->properties[self::PROPERTY_DEVICE_SERIAL] ?? '';
        $this->status = $this->properties[self::PROPERTY_UPS_STATUS] ?? '';
        $this->power = isset($this->properties[self::PROPERTY_UPS_POWER]) ? intval($this->properties[self::PROPERTY_UPS_POWER]) : null;
        $this->realPower = isset($this->properties[self::PROPERTY_UPS_REAL_POWER]) ? intval($this->properties[self::PROPERTY_UPS_REAL_POWER]) : null;
        $this->batteryLevel = isset($this->properties[self::PROPERTY_BATTERY_CHARGE]) ? intval($this->properties[self::PROPERTY_BATTERY_CHARGE]) : null;
        $this->batteryRuntime = isset($this->properties[self::PROPERTY_BATTERY_RUNTIME]) ? intval($this->properties[self::PROPERTY_BATTERY_RUNTIME]) : null;
        $this->batteryRuntimeLow = isset($this->properties[self::PROPERTY_BATTERY_RUNTIME_LOW]) ? intval($this->properties[self::PROPERTY_BATTERY_RUNTIME_LOW]) : null;

        $this->isOnBattery = str_contains($this->status, self::PROPERTY_VALUE_UPS_STATUS_ON_BATTERY);
        $this->isBatteryRuntimeLow = $this->batteryRuntimeLow && $this->batteryRuntime && $this->batteryRuntime <= $this->batteryRuntimeLow;
    }

    public function toArray(): array
    {
        $powerInfo = sprintf(
            "P: %s W\nS: %s VA",
            $this->getRealPower() ?? '-',
            $this->getPower() ?? '-'
        );

        $batteryInfo = sprintf(
            "Current: %s%%\nRuntime: %s min\nRuntime Low: %s min",
            $this->getBatteryLevel() ?? '-',
            $this->getBatteryRuntime() ? round($this->batteryRuntime / 60) : 'N/A',
            $this->getBatteryRuntimeLow() ? round($this->batteryRuntimeLow / 60) : 'N/A'
        );

        return [
            'name' => $this->getName(),
            'model_name' => $this->getModelName(),
            'serial_number' => $this->getSerialNumber(),
            'status' => $this->getStatus(),
            'power' => $powerInfo,
            'battery' => $batteryInfo
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getModelName(): ?string
    {
        return $this->modelName;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getPower(): ?int
    {
        return $this->power;
    }

    public function getRealPower(): ?int
    {
        return $this->realPower;
    }

    public function getBatteryLevel(): ?int
    {
        return $this->batteryLevel;
    }

    public function getBatteryRuntime(): ?int
    {
        return $this->batteryRuntime;
    }

    public function getBatteryRuntimeLow(): ?int
    {
        return $this->batteryRuntimeLow;
    }

    public function isOnBattery(): ?bool
    {
        return $this->isOnBattery;
    }

    public function isBatteryRuntimeLow(): ?bool
    {
        return $this->isBatteryRuntimeLow;
    }
}
