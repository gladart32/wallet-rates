<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260613230353 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create merchant, provider, rate tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE merchant (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            name VARCHAR(128) NOT NULL,
            api_key VARCHAR(128) NOT NULL,
            api_secret VARCHAR(256) NOT NULL,
            base_currency VARCHAR(16) NOT NULL,
            status ENUM(\'active\', \'disabled\', \'deleted\') NOT NULL DEFAULT \'active\',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_merchant_api_key (api_key)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE provider (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            name VARCHAR(128) NOT NULL,
            data JSON NOT NULL DEFAULT (JSON_OBJECT()),
            status ENUM(\'active\', \'disabled\', \'deleted\') NOT NULL DEFAULT \'active\',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE rate (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            provider VARCHAR(32) NOT NULL,
            currency_from VARCHAR(16) NOT NULL,
            currency_to VARCHAR(16) NOT NULL,
            value DECIMAL(36, 18) NOT NULL,
            status ENUM(\'active\', \'disabled\', \'deleted\') NOT NULL DEFAULT \'active\',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_rate_provider_pair (provider, currency_from, currency_to)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rate');
        $this->addSql('DROP TABLE provider');
        $this->addSql('DROP TABLE merchant');
    }
}
