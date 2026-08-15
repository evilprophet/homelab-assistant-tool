<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Service\Application;

use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\Device;
use EvilStudio\HAT\Entity\Ups;
use EvilStudio\HAT\Exception\EntityAlreadyExists;
use EvilStudio\HAT\Exception\EntityNotFound;
use EvilStudio\HAT\Repository\UpsRepository;
use InvalidArgumentException;

class UpsService extends AbstractDatabaseService
{
    // Anchored on an alphanumeric so the identifier cannot start with '-', which
    // upsc would parse as an option rather than a UPS name.
    protected const string IDENTIFIER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';
    // Labels of letters, digits and inner dashes only, so '-', '...' and 'a-' are rejected.
    protected const string HOSTNAME_PATTERN =
        '/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)*$/';

    public function __construct(
        EntityManagerInterface $entityManager,
        protected UpsRepository $upsRepository
    ) {
        parent::__construct($entityManager);
    }

    public function listUps(): array
    {
        return $this->upsRepository->findAll();
    }

    public function getUpsById(int $upsId): Ups
    {
        $ups = $this->upsRepository->findById($upsId);
        if ($ups === null) {
            throw EntityNotFound::forField('UPS', 'id', $upsId);
        }

        return $ups;
    }

    public function getUpsByIdentifier(string $identifier): Ups
    {
        $ups = $this->upsRepository->findOneByIdentifier($identifier);
        if ($ups === null) {
            throw EntityNotFound::forField('UPS', 'identifier', $identifier);
        }

        return $ups;
    }

    public function createUps(
        string $name,
        string $identifier,
        string $host,
        ?int $safeBatteryRuntimeThreshold = null
    ): Ups {
        $normalizedName = $this->normalizeName($name);
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);
        $normalizedHost = $this->normalizeHost($host);
        $this->assertThresholdIsNonNegative($safeBatteryRuntimeThreshold);

        $this->ensureIdentifierIsUnique($normalizedIdentifier);

        $ups = new Ups();
        $ups
            ->setName($normalizedName)
            ->setIdentifier($normalizedIdentifier)
            ->setHost($normalizedHost)
            ->setSafeBatteryRuntimeThreshold($safeBatteryRuntimeThreshold);

        $this->persist($ups);
        $this->flushExpectingUnique('UPS', 'identifier', $normalizedIdentifier);

        return $ups;
    }

    public function updateUps(
        int $upsId,
        string $name,
        string $identifier,
        string $host,
        ?int $safeBatteryRuntimeThreshold = null
    ): Ups {
        $normalizedName = $this->normalizeName($name);
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);
        $normalizedHost = $this->normalizeHost($host);
        $this->assertThresholdIsNonNegative($safeBatteryRuntimeThreshold);
        $ups = $this->getUpsById($upsId);
        $this->ensureIdentifierIsUnique($normalizedIdentifier, $upsId);

        $ups
            ->setName($normalizedName)
            ->setIdentifier($normalizedIdentifier)
            ->setHost($normalizedHost)
            ->setSafeBatteryRuntimeThreshold($safeBatteryRuntimeThreshold);

        $this->flushExpectingUnique('UPS', 'identifier', $normalizedIdentifier);

        return $ups;
    }

    public function removeUps(int $upsId): void
    {
        $ups = $this->getUpsById($upsId);

        $this->runInTransaction(function () use ($ups): void {
            foreach ($ups->getDevices()->toArray() as $device) {
                if ($device instanceof Device) {
                    $device->setUps(null);
                }
            }

            $this->remove($ups);
        });
    }

    protected function normalizeName(string $name): string
    {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw new InvalidArgumentException('UPS name cannot be empty.');
        }

        return $normalizedName;
    }

    protected function assertThresholdIsNonNegative(?int $threshold): void
    {
        if ($threshold !== null && $threshold < 0) {
            throw new InvalidArgumentException('Safe battery runtime threshold cannot be negative.');
        }
    }

    protected function ensureIdentifierIsUnique(string $identifier, ?int $excludeUpsId = null): void
    {
        $existingUps = $this->upsRepository->findOneByIdentifier($identifier);
        if ($existingUps === null) {
            return;
        }

        if ($excludeUpsId !== null && $existingUps->getId() === $excludeUpsId) {
            return;
        }

        throw EntityAlreadyExists::forField('UPS', 'identifier', $identifier);
    }

    protected function normalizeIdentifier(string $identifier): string
    {
        $normalizedIdentifier = trim($identifier);
        if ($normalizedIdentifier === '') {
            throw new InvalidArgumentException('UPS identifier cannot be empty.');
        }

        if (preg_match(self::IDENTIFIER_PATTERN, $normalizedIdentifier) !== 1) {
            throw new InvalidArgumentException(
                'UPS identifier can contain only letters, digits, dot, underscore, and dash, '
                . 'and must start with a letter or digit.'
            );
        }

        return $normalizedIdentifier;
    }

    protected function normalizeHost(string $host): string
    {
        $normalizedHost = trim($host);
        if ($normalizedHost === '') {
            throw new InvalidArgumentException('UPS host cannot be empty.');
        }

        [$hostPart, $port] = $this->splitHostAndPort($normalizedHost);

        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException('UPS host port must be between 1 and 65535.');
        }

        if (!$this->isValidHostPart($hostPart)) {
            throw new InvalidArgumentException(
                'UPS host must be a hostname, IPv4 address, or bracketed IPv6 address with optional :port ' .
                '(for example ups.local, 192.168.1.10:3493 or [fd00::10]:3493).'
            );
        }

        return $normalizedHost;
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    protected function splitHostAndPort(string $host): array
    {
        if (str_starts_with($host, '[')) {
            $closingBracket = strpos($host, ']');
            if ($closingBracket === false) {
                return ['', null];
            }

            $remainder = substr($host, $closingBracket + 1);
            $hostPart = substr($host, 1, $closingBracket - 1);
            if ($remainder === '') {
                return [$hostPart, null];
            }

            return str_starts_with($remainder, ':')
                ? [$hostPart, $this->parsePort(substr($remainder, 1))]
                : ['', null];
        }

        // More than one colon means a bare IPv6 literal, which carries no port.
        if (!str_contains($host, ':') || substr_count($host, ':') > 1) {
            return [$host, null];
        }

        [$hostPart, $rawPort] = explode(':', $host, 2);

        return [$hostPart, $this->parsePort($rawPort)];
    }

    protected function parsePort(string $rawPort): int
    {
        $port = filter_var($rawPort, FILTER_VALIDATE_INT);

        return $port === false ? 0 : (int)$port;
    }

    protected function isValidHostPart(string $hostPart): bool
    {
        if ($hostPart === '') {
            return false;
        }

        if (filter_var($hostPart, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        // Digits and dots that failed the IP check are malformed addresses such as
        // 192.168.1.999, which the hostname rule would otherwise accept.
        if (preg_match('/^[0-9.]+$/', $hostPart) === 1) {
            return false;
        }

        return preg_match(self::HOSTNAME_PATTERN, $hostPart) === 1;
    }
}
