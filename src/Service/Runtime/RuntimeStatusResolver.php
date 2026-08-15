<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Runtime;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

class RuntimeStatusResolver
{
    protected const int STATUS_CACHE_TTL_SECONDS = 60;

    public function __construct(
        protected DeviceOperationsService $deviceOperationsService,
        protected UpsRuntimeService $upsRuntimeService,
        #[Autowire(service: 'cache.app')]
        protected CacheInterface $cache,
        protected ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Without this a start/stop redirect re-renders the pre-action status for up to
     * a minute, so the UI contradicts the success message the user just got.
     */
    public function invalidateDeviceStatus(string $deviceName): void
    {
        $normalizedNames = $this->normalizeIdentifiers([$deviceName]);
        if ($normalizedNames === []) {
            return;
        }

        $this->cache->delete($this->buildDeviceStatusCacheKey($normalizedNames[0]));
    }

    public function resolveDeviceStatusByNames(array $deviceNames): array
    {
        $resolvedStatuses = [];

        foreach ($this->normalizeIdentifiers($deviceNames) as $deviceName) {
            $resolvedStatuses[$deviceName] = $this->cache->get(
                $this->buildDeviceStatusCacheKey($deviceName),
                function (ItemInterface $item) use ($deviceName): string {
                    $item->expiresAfter(self::STATUS_CACHE_TTL_SECONDS);

                    try {
                        $runtimeDevice = $this->deviceOperationsService->checkDeviceStatus($deviceName);
                        $runtimeData = $runtimeDevice->toArray();

                        return (string)($runtimeData['status'] ?? 'unknown');
                    } catch (Throwable $exception) {
                        // Swallowed on purpose - the UI degrades to a grey dot - but a
                        // persistent misconfiguration must leave a trail somewhere.
                        $this->logStatusFailure('device', $deviceName, $exception);

                        return 'unknown';
                    }
                }
            );
        }

        return $resolvedStatuses;
    }

    public function resolveUpsStatusByIdentifiers(array $upsIdentifiers): array
    {
        $resolvedStatuses = [];
        $upsDataByIdentifier = $this->resolveUpsDataByIdentifiers($upsIdentifiers);

        foreach ($upsDataByIdentifier as $upsIdentifier => $runtimeData) {
            $resolvedStatuses[$upsIdentifier] = $this->buildUpsStatusFromRuntimeData($runtimeData);
        }

        return $resolvedStatuses;
    }

    public function resolveUpsDataByIdentifiers(array $upsIdentifiers): array
    {
        $resolvedData = [];

        foreach ($this->normalizeIdentifiers($upsIdentifiers) as $upsIdentifier) {
            $resolvedData[$upsIdentifier] = $this->cache->get(
                $this->buildUpsDataCacheKey($upsIdentifier),
                function (ItemInterface $item) use ($upsIdentifier): array {
                    $item->expiresAfter(self::STATUS_CACHE_TTL_SECONDS);

                    try {
                        $runtimeUps = $this->upsRuntimeService->getRuntimeUpsByIdentifier($upsIdentifier);
                        $runtimeUps->updateStatus();

                        return $runtimeUps->toArray();
                    } catch (Throwable $exception) {
                        $this->logStatusFailure('ups', $upsIdentifier, $exception);

                        return [
                            'id' => '-',
                            'name' => '-',
                            'identifier' => $upsIdentifier,
                            'model_name' => '-',
                            'serial_number' => '-',
                            'status' => '-',
                            'power' => '-',
                            'battery' => '-',
                            'linked_devices' => [],
                        ];
                    }
                }
            );
        }

        return $resolvedData;
    }

    protected function logStatusFailure(string $kind, string $identifier, Throwable $exception): void
    {
        $this->logger?->warning('Runtime status lookup failed.', [
            'kind' => $kind,
            'identifier' => $identifier,
            'exception' => $exception->getMessage(),
        ]);
    }

    protected function normalizeIdentifiers(array $rawValues): array
    {
        $normalizedValues = [];

        foreach ($rawValues as $rawValue) {
            if (!is_scalar($rawValue)) {
                continue;
            }

            $value = trim((string)$rawValue);
            if ($value === '') {
                continue;
            }

            $normalizedValues[] = $value;
        }

        return array_values(array_unique($normalizedValues));
    }

    protected function buildDeviceStatusCacheKey(string $deviceName): string
    {
        return sprintf('runtime.device_status.%s', md5($deviceName));
    }

    protected function buildUpsDataCacheKey(string $upsIdentifier): string
    {
        return sprintf('runtime.ups_data.%s', md5($upsIdentifier));
    }

    protected function buildUpsStatusFromRuntimeData(array $runtimeData): array
    {
        $status = trim((string)($runtimeData['status'] ?? 'Unknown'));
        $batteryLines = isset($runtimeData['battery']) && is_string($runtimeData['battery'])
            ? explode("\n", $runtimeData['battery'])
            : [];

        $batteryLevel = null;
        $batteryRuntimeMinutes = null;

        foreach ($batteryLines as $batteryLine) {
            if ($batteryLevel === null && preg_match('/Current:\s*([0-9]+)%/i', $batteryLine, $levelMatches) === 1) {
                $batteryLevel = (int)$levelMatches[1];
            }

            if (
                $batteryRuntimeMinutes === null
                && preg_match('/Runtime:\s*([0-9]+)\s*min/i', $batteryLine, $runtimeMatches) === 1
            ) {
                $batteryRuntimeMinutes = (int)$runtimeMatches[1];
            }
        }

        if ($status === 'On Battery') {
            return [
                'label' => 'On Battery',
                'tone' => 'warning',
                'battery_level' => $batteryLevel,
                'battery_runtime_minutes' => $batteryRuntimeMinutes,
            ];
        }

        if ($status === 'Online') {
            return [
                'label' => 'Online',
                'tone' => 'success',
                'battery_level' => $batteryLevel,
                'battery_runtime_minutes' => $batteryRuntimeMinutes,
            ];
        }

        return [
            'label' => 'Unknown',
            'tone' => 'neutral',
            'battery_level' => $batteryLevel,
            'battery_runtime_minutes' => $batteryRuntimeMinutes,
        ];
    }
}
