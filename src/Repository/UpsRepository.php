<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Repository;

use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\Ups;

class UpsRepository
{
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
    }

    public function findAll(): array
    {
        $upsCollection = $this->entityManager->getRepository(Ups::class)->findBy([], ['id' => 'ASC']);

        return array_values(array_filter($upsCollection, static fn ($ups) => $ups instanceof Ups));
    }

    public function findById(int $id): ?Ups
    {
        $ups = $this->entityManager->getRepository(Ups::class)->find($id);

        return $ups instanceof Ups ? $ups : null;
    }

    public function findOneByIdentifier(string $identifier): ?Ups
    {
        $ups = $this->entityManager->getRepository(Ups::class)->findOneBy(['identifier' => $identifier]);

        return $ups instanceof Ups ? $ups : null;
    }
}
