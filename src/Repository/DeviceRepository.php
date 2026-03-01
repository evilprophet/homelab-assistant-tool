<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Repository;

use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\Device;

class DeviceRepository
{
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
    }

    public function findAll(): array
    {
        $devices = $this->entityManager->getRepository(Device::class)->findBy([], ['id' => 'ASC']);

        return array_values(array_filter($devices, static fn ($device) => $device instanceof Device));
    }

    public function findById(int $id): ?Device
    {
        $device = $this->entityManager->getRepository(Device::class)->find($id);

        return $device instanceof Device ? $device : null;
    }

    public function findOneByName(string $name): ?Device
    {
        $device = $this->entityManager->getRepository(Device::class)->findOneBy(['name' => $name]);

        return $device instanceof Device ? $device : null;
    }

    public function findByIds(array $deviceIds): array
    {
        if (empty($deviceIds)) {
            return [];
        }

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('device')
            ->from(Device::class, 'device')
            ->where($queryBuilder->expr()->in('device.id', ':ids'))
            ->setParameter('ids', $deviceIds);

        $devices = $queryBuilder->getQuery()->getResult();

        return array_values(array_filter($devices, static fn ($device) => $device instanceof Device));
    }
}
