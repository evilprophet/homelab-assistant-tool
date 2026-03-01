<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Repository;

use Doctrine\ORM\EntityManagerInterface;
use EvilStudio\HAT\Entity\Schedule;

class ScheduleRepository
{
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
    }

    public function findAll(): array
    {
        $schedules = $this->entityManager->getRepository(Schedule::class)->findBy([], ['id' => 'ASC']);

        return array_values(array_filter($schedules, static fn ($schedule) => $schedule instanceof Schedule));
    }

    public function findAllEnabled(): array
    {
        $schedules = $this->entityManager->getRepository(Schedule::class)->findBy(
            ['isEnabled' => true],
            ['id' => 'ASC']
        );

        return array_values(array_filter($schedules, static fn ($schedule) => $schedule instanceof Schedule));
    }

    public function findById(int $id): ?Schedule
    {
        $schedule = $this->entityManager->getRepository(Schedule::class)->find($id);

        return $schedule instanceof Schedule ? $schedule : null;
    }

    public function findOneByName(string $name): ?Schedule
    {
        $schedule = $this->entityManager->getRepository(Schedule::class)->findOneBy(['name' => $name]);

        return $schedule instanceof Schedule ? $schedule : null;
    }
}
