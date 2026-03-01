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
    protected const string IDENTIFIER_PATTERN = '/^[A-Za-z0-9._-]+$/';
    protected const string HOST_PATTERN = '/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/';

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
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);
        $normalizedHost = $this->normalizeHost($host);

        $this->ensureIdentifierIsUnique($normalizedIdentifier);

        $ups = new Ups();
        $ups
            ->setName($name)
            ->setIdentifier($normalizedIdentifier)
            ->setHost($normalizedHost)
            ->setSafeBatteryRuntimeThreshold($safeBatteryRuntimeThreshold);

        $this->persist($ups);
        $this->flush();

        return $ups;
    }

    public function updateUps(
        int $upsId,
        string $name,
        string $identifier,
        string $host,
        ?int $safeBatteryRuntimeThreshold = null
    ): Ups {
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);
        $normalizedHost = $this->normalizeHost($host);
        $ups = $this->getUpsById($upsId);
        $this->ensureIdentifierIsUnique($normalizedIdentifier, $upsId);

        $ups
            ->setName($name)
            ->setIdentifier($normalizedIdentifier)
            ->setHost($normalizedHost)
            ->setSafeBatteryRuntimeThreshold($safeBatteryRuntimeThreshold);

        $this->flush();

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
                'UPS identifier can contain only letters, digits, dot, underscore, and dash.'
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

        if (preg_match(self::HOST_PATTERN, $normalizedHost) !== 1) {
            throw new InvalidArgumentException(
                'UPS host must be a valid hostname or IP with optional :port ' .
                '(for example ups.local or 192.168.1.10:3493).'
            );
        }

        if (!str_contains($normalizedHost, ':')) {
            return $normalizedHost;
        }

        [, $rawPort] = explode(':', $normalizedHost, 2);
        $port = (int)$rawPort;
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('UPS host port must be between 1 and 65535.');
        }

        return $normalizedHost;
    }
}
