<?php

declare(strict_types=1);

namespace EvilStudio\HAT\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260228183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for HAT';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE action_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, source VARCHAR(16) NOT NULL, "action" VARCHAR(64) NOT NULL, level VARCHAR(16) NOT NULL, message CLOB NOT NULL, created_at DATETIME NOT NULL)');

        $this->addSql('CREATE TABLE ups (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, identifier VARCHAR(255) NOT NULL, host VARCHAR(255) NOT NULL, safe_battery_runtime_threshold INTEGER UNSIGNED DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4B482AD9772E836A ON ups (identifier)');

        $this->addSql('CREATE TABLE devices (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, ip VARCHAR(45) NOT NULL, mac VARCHAR(17) NOT NULL, platform VARCHAR(64) NOT NULL, username VARCHAR(255) DEFAULT NULL, ups_low_battery_runtime_threshold INTEGER UNSIGNED DEFAULT NULL, ups_id INTEGER DEFAULT NULL, CONSTRAINT FK_11074E9AF375CE4E FOREIGN KEY (ups_id) REFERENCES ups (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_11074E9A5E237E06 ON devices (name)');
        $this->addSql('CREATE INDEX IDX_11074E9AF375CE4E ON devices (ups_id)');

        $this->addSql('CREATE TABLE schedules (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, is_enabled BOOLEAN DEFAULT 1 NOT NULL, cron_expression VARCHAR(100) NOT NULL, command VARCHAR(32) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_313BDC8E5E237E06 ON schedules (name)');

        $this->addSql('CREATE TABLE schedule_device (schedule_id INTEGER NOT NULL, device_id INTEGER NOT NULL, PRIMARY KEY (schedule_id, device_id), CONSTRAINT FK_6B0EF291A40BC2D5 FOREIGN KEY (schedule_id) REFERENCES schedules (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6B0EF29194A4C7D4 FOREIGN KEY (device_id) REFERENCES devices (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6B0EF291A40BC2D5 ON schedule_device (schedule_id)');
        $this->addSql('CREATE INDEX IDX_6B0EF29194A4C7D4 ON schedule_device (device_id)');

        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(128) NOT NULL, password_hash VARCHAR(255) DEFAULT NULL, oidc_subject VARCHAR(191) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9F85E0677 ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9C75039CC ON users (oidc_subject)');
        $this->addSql('CREATE INDEX idx_users_username ON users (username)');
        $this->addSql('CREATE INDEX idx_users_oidc_subject ON users (oidc_subject)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE schedule_device');
        $this->addSql('DROP TABLE schedules');
        $this->addSql('DROP TABLE devices');
        $this->addSql('DROP TABLE ups');
        $this->addSql('DROP TABLE action_logs');
        $this->addSql('DROP TABLE users');
    }
}
