<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Contract;

enum DevicePlatform: string
{
    case GENERIC = 'generic';
    case SYNOLOGY_DSM = 'synology_dsm';
    case ASUSTOR_ADM = 'asustor_adm';
    case QNAP_QTS = 'qnap_qts';
    case LINUX = 'linux';
    case DEBIAN = 'debian';
    case UBUNTU = 'ubuntu';
    case PROXMOX_DM = 'proxmox_dm';
    case PROXMOX_BS = 'proxmox_bs';
    case PROXMOX_VE = 'proxmox_ve';

    public static function values(): array
    {
        return array_map(static fn (self $platform): string => $platform->value, self::cases());
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $platform) {
            $labels[$platform->value] = $platform->label();
        }

        return $labels;
    }

    public function label(): string
    {
        return match ($this) {
            self::GENERIC => 'Generic',
            self::SYNOLOGY_DSM => 'Synology DSM',
            self::ASUSTOR_ADM => 'Asustor ADM',
            self::QNAP_QTS => 'QNAP QTS',
            self::LINUX => 'Linux',
            self::DEBIAN => 'Debian',
            self::UBUNTU => 'Ubuntu',
            self::PROXMOX_DM => 'Proxmox DM',
            self::PROXMOX_BS => 'Proxmox BS',
            self::PROXMOX_VE => 'Proxmox VE',
        };
    }

    public static function linuxRuntimeMap(): array
    {
        $map = [];
        foreach (self::cases() as $platform) {
            $map[$platform->value] = $platform->usesLinuxRuntime();
        }

        return $map;
    }

    public function usesLinuxRuntime(): bool
    {
        return match ($this) {
            self::LINUX, self::DEBIAN, self::UBUNTU, self::PROXMOX_DM, self::PROXMOX_BS, self::PROXMOX_VE => true,
            default => false
        };
    }

    public function supportedActions(): array
    {
        return match ($this) {
            self::LINUX, self::DEBIAN, self::UBUNTU, self::PROXMOX_DM, self::PROXMOX_BS, self::PROXMOX_VE => [
                DeviceAction::START,
                DeviceAction::STOP,
                DeviceAction::SSH,
            ],
            default => [DeviceAction::START],
        };
    }

    public function supportsAction(DeviceAction $action): bool
    {
        return in_array($action, $this->supportedActions(), true);
    }
}
