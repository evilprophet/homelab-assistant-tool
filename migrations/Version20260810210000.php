<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add action log indexes declared by the entity but never created';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_action_logs_created_at ON action_logs (created_at)');
        $this->addSql('CREATE INDEX idx_action_logs_source ON action_logs (source)');
        $this->addSql('CREATE INDEX idx_action_logs_level ON action_logs (level)');
        $this->addSql('CREATE INDEX idx_action_logs_action ON action_logs (action)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_action_logs_created_at');
        $this->addSql('DROP INDEX idx_action_logs_source');
        $this->addSql('DROP INDEX idx_action_logs_level');
        $this->addSql('DROP INDEX idx_action_logs_action');
    }
}
