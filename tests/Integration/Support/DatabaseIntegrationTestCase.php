<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Throwable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class DatabaseIntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = self::$kernel->getContainer();
        $this->entityManager = $container->get('doctrine.orm.default_entity_manager');

        $this->recreateDatabaseSchema();
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        $this->entityManager->getConnection()->close();
        self::ensureKernelShutdown();

        parent::tearDown();
    }

    protected function recreateDatabaseSchema(): void
    {
        $container = self::$kernel->getContainer();
        $projectDir = (string)$container->getParameter('kernel.project_dir');
        $databasePath = $projectDir . '/' . ltrim((string)$container->getParameter('sqlite_database_path'), '/');
        $directory = dirname($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if (empty($metadata)) {
            return;
        }

        $schemaTool = new SchemaTool($this->entityManager);
        try {
            $schemaTool->dropSchema($metadata);
        } catch (Throwable) {
        }

        $schemaTool->createSchema($metadata);
    }
}
