<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auto stop flag for devices';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE devices ADD auto_stop_allowed BOOLEAN DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE devices DROP auto_stop_allowed');
    }
}

