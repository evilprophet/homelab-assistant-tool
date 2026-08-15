<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Tests\Integration\Migration;

use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use EvilStudio\HAT\Tests\Integration\Support\DatabaseIntegrationTestCase;
use RuntimeException;

class MigrationSchemaDriftTest extends DatabaseIntegrationTestCase
{
    protected const string TEST_DATABASE_MARKER = 'hat_test.sqlite';

    public function testMigrationsCreateEveryTableTheEntitiesDeclare(): void
    {
        $this->migrateFromEmptyDatabase();

        $existingTables = $this->fetchTableNames();

        foreach ($this->entityTableNames() as $tableName) {
            $this->assertContains(
                $tableName,
                $existingTables,
                sprintf("Migrations do not create table '%s' declared by an entity.", $tableName)
            );
        }
    }

    public function testMigrationsCreateEveryNamedIndexTheEntitiesDeclare(): void
    {
        $this->migrateFromEmptyDatabase();

        $existingIndexes = $this->entityManager->getConnection()->fetchFirstColumn(
            "SELECT name FROM sqlite_master WHERE type = 'index' AND name NOT LIKE 'sqlite_%'"
        );

        foreach ($this->entityIndexNames() as $indexName) {
            $this->assertContains(
                $indexName,
                $existingIndexes,
                sprintf("Migrations do not create index '%s' declared by an entity.", $indexName)
            );
        }
    }

    protected function migrateFromEmptyDatabase(): void
    {
        $this->assertDisposableTestDatabase();

        $connection = $this->entityManager->getConnection();
        $schemaTool = new SchemaTool($this->entityManager);

        // The base class leaves a schema built from entity metadata. Migrations have to
        // start from an empty database, the way a production install does.
        $connection->executeStatement('PRAGMA foreign_keys = OFF');
        $schemaTool->dropSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
        foreach ($this->fetchTableNames() as $tableName) {
            $connection->executeStatement(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));
        }
        $connection->executeStatement('PRAGMA foreign_keys = ON');

        $dependencyFactory = static::getContainer()->get('doctrine.migrations.dependency_factory');
        $dependencyFactory->getMetadataStorage()->ensureInitialized();

        $plan = $dependencyFactory->getMigrationPlanCalculator()->getPlanUntilVersion(
            $dependencyFactory->getVersionAliasResolver()->resolveVersionAlias('latest')
        );

        $dependencyFactory->getMigrator()->migrate($plan, new MigratorConfiguration());
    }

    protected function fetchTableNames(): array
    {
        return $this->entityManager->getConnection()->fetchFirstColumn(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
        );
    }

    protected function entityTableNames(): array
    {
        return array_map(
            static fn (ClassMetadata $metadata): string => $metadata->getTableName(),
            $this->entityManager->getMetadataFactory()->getAllMetadata()
        );
    }

    protected function entityIndexNames(): array
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schema = $schemaTool->getSchemaFromMetadata($this->entityManager->getMetadataFactory()->getAllMetadata());

        $indexNames = [];
        foreach ($schema->getTables() as $table) {
            foreach ($table->getIndexes() as $index) {
                // Doctrine derives IDX_/UNIQ_ names from a column hash, and those hashes
                // drift harmlessly. Only names written in an entity are worth asserting.
                if ($index->isPrimary() || preg_match('/^(IDX|UNIQ)_[0-9A-F]{8,}$/', $index->getName()) === 1) {
                    continue;
                }

                $indexNames[] = $index->getName();
            }
        }

        return $indexNames;
    }

    protected function assertDisposableTestDatabase(): void
    {
        $databasePath = (string)static::getContainer()->getParameter('sqlite_database_path');

        if (!str_contains($databasePath, self::TEST_DATABASE_MARKER)) {
            throw new RuntimeException(
                sprintf("Refusing to drop tables: '%s' is not the disposable test database.", $databasePath)
            );
        }

        $this->addToAssertionCount(1);
    }
}
