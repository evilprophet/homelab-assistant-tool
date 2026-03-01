<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Repository;

use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\User;

class UserRepository
{
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
    }

    public function findById(int $id): ?User
    {
        $entity = $this->entityManager->getRepository(User::class)->find($id);

        return $entity instanceof User ? $entity : null;
    }

    public function findByUsername(string $username): ?User
    {
        $entity = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);

        return $entity instanceof User ? $entity : null;
    }

    public function findByOidcSubject(string $oidcSubject): ?User
    {
        $entity = $this->entityManager->getRepository(User::class)->findOneBy(['oidcSubject' => $oidcSubject]);

        return $entity instanceof User ? $entity : null;
    }
}
