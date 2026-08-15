<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop user indexes duplicating the unique indexes on username and oidc_subject';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_users_username');
        $this->addSql('DROP INDEX IF EXISTS idx_users_oidc_subject');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_users_username ON users (username)');
        $this->addSql('CREATE INDEX idx_users_oidc_subject ON users (oidc_subject)');
    }
}
