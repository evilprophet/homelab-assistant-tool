<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Runtime;

use EvilStudio\HAT\Contract\UpsInterface;
use EvilStudio\HAT\Exception\UpsFailedUpdateStatus;
use Symfony\Component\Process\Process;

class Ups implements UpsInterface
{
    protected const string UPSC_BINARY = 'upsc';
    protected const int UPSC_TIMEOUT_SECONDS = 15;

    protected const string PROPERTY_DEVICE_MODEL = 'device.model';
    protected const string PROPERTY_DEVICE_SERIAL = 'device.serial';
    protected const string PROPERTY_UPS_STATUS = 'ups.status';
    protected const string PROPERTY_UPS_POWER = 'ups.power';
    protected const string PROPERTY_UPS_REAL_POWER = 'ups.realpower';
    protected const string PROPERTY_BATTERY_RUNTIME = 'battery.runtime';
    protected const string PROPERTY_BATTERY_RUNTIME_LOW = 'battery.runtime.low';
    protected const string PROPERTY_BATTERY_CHARGE = 'battery.charge';
    protected const string PROPERTY_VALUE_UPS_STATUS_ON_BATTERY = 'OB';

    protected const string STATUS_LABEL_ONLINE = 'Online';
    protected const string STATUS_LABEL_ON_BATTERY = 'On Battery';
    protected const string STATUS_LABEL_UNKNOWN = 'Unknown';

    protected array $properties = [];
    protected ?string $modelName = null;
    protected ?string $serialNumber = null;
    protected ?string $status = '';
    protected ?int $power = null;
    protected ?int $realPower = null;
    protected ?int $batteryLevel = null;
    protected ?int $batteryRuntime = null;
    protected ?int $lowBatteryRuntimeThreshold = null;

    public function __construct(
        protected ?int $id,
        protected string $name,
        protected string $identifier,
        protected string $host,
        protected ?int $safeBatteryRuntimeThreshold,
        protected array $linkedDevices = []
    ) {
    }

    public function updateStatus(): void
    {
        $target = sprintf('%s@%s', $this->getIdentifier(), $this->getHost());
        $output = [];
        $resultCode = 0;

        $this->executeCommand($target, $output, $resultCode);

        if ($resultCode !== 0) {
            throw new UpsFailedUpdateStatus(
                sprintf("Cannot load status for UPS '%s' at '%s'.", $this->getIdentifier(), $this->getHost())
            );
        }

        // Without this a second poll on the same object keeps values from the first one.
        $this->properties = [];

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
        $this->power = isset($this->properties[self::PROPERTY_UPS_POWER])
            ? intval($this->properties[self::PROPERTY_UPS_POWER])
            : null;
        $this->realPower = isset($this->properties[self::PROPERTY_UPS_REAL_POWER])
            ? intval($this->properties[self::PROPERTY_UPS_REAL_POWER])
            : null;
        $this->batteryLevel = isset($this->properties[self::PROPERTY_BATTERY_CHARGE])
            ? intval($this->properties[self::PROPERTY_BATTERY_CHARGE])
            : null;
        $this->batteryRuntime = isset($this->properties[self::PROPERTY_BATTERY_RUNTIME])
            ? intval($this->properties[self::PROPERTY_BATTERY_RUNTIME])
            : null;
        $this->lowBatteryRuntimeThreshold = isset($this->properties[self::PROPERTY_BATTERY_RUNTIME_LOW])
            ? intval($this->properties[self::PROPERTY_BATTERY_RUNTIME_LOW])
            : null;
    }

    public function toArray(): array
    {
        $modelName = $this->normalizeText($this->getModelName());
        $serialNumber = $this->normalizeText($this->getSerialNumber());
        $status = $this->resolveStatusLabel($this->getStatus());

        $powerInfo = sprintf(
            "P: %s W\nS: %s VA",
            $this->getRealPower() ?? '-',
            $this->getPower() ?? '-'
        );

        $batteryInfo = sprintf(
            "Current: %s%%\nRuntime: %s min\nLow Runtime Threshold: %s min\nSafe Runtime Threshold: %s min",
            $this->getBatteryLevel() ?? '-',
            $this->getBatteryRuntime() !== null ? round($this->getBatteryRuntime() / 60) : 'N/A',
            $this->getLowBatteryRuntimeThreshold() !== null
                ? round($this->getLowBatteryRuntimeThreshold() / 60)
                : 'N/A',
            $this->getSafeBatteryRuntimeThreshold() !== null
                ? round($this->getSafeBatteryRuntimeThreshold() / 60)
                : 'N/A'
        );

        return [
            'id' => $this->getId() ?? '-',
            'name' => $this->getName(),
            'identifier' => $this->getIdentifier(),
            'model_name' => $modelName,
            'serial_number' => $serialNumber,
            'status' => $status,
            'power' => $powerInfo,
            'battery' => $batteryInfo,
            // Structured, because joining names into one string loses the id link and
            // breaks apart again on any name containing the delimiter.
            'linked_devices' => $this->getLinkedDevices(),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getLowBatteryRuntimeThreshold(): ?int
    {
        return $this->lowBatteryRuntimeThreshold;
    }

    public function getSafeBatteryRuntimeThreshold(): ?int
    {
        return $this->safeBatteryRuntimeThreshold;
    }

    public function getLinkedDevices(): array
    {
        return $this->linkedDevices;
    }

    public function isOnBattery(): bool
    {
        return str_contains($this->getStatus(), self::PROPERTY_VALUE_UPS_STATUS_ON_BATTERY);
    }

    public function isBatteryRuntimeLow(): bool
    {
        $lowBatteryRuntimeThreshold = $this->getLowBatteryRuntimeThreshold();
        $batteryRuntime = $this->getBatteryRuntime();

        return $lowBatteryRuntimeThreshold !== null
            && $batteryRuntime !== null
            && $batteryRuntime <= $lowBatteryRuntimeThreshold;
    }

    protected function executeCommand(string $target, array &$output, int &$resultCode): void
    {
        $process = new Process([self::UPSC_BINARY, $target]);
        $process->setTimeout(self::UPSC_TIMEOUT_SECONDS);
        $process->run();

        $resultCode = $process->getExitCode() ?? 1;
        $rawOutput = $process->getOutput();
        if ($rawOutput === '') {
            $output = [];

            return;
        }

        $lines = preg_split('/\R/', rtrim($rawOutput));
        $output = is_array($lines) ? $lines : [];
    }

    protected function normalizeText(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '-';
        }

        return $value;
    }

    protected function resolveStatusLabel(?string $status): string
    {
        $statusRaw = trim((string)$status);
        if ($statusRaw === '' || $statusRaw === '-') {
            return self::STATUS_LABEL_UNKNOWN;
        }

        if (str_contains($statusRaw, self::PROPERTY_VALUE_UPS_STATUS_ON_BATTERY)) {
            return self::STATUS_LABEL_ON_BATTERY;
        }

        return self::STATUS_LABEL_ONLINE;
    }
}
