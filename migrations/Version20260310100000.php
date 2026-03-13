<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create rememberme_token table for Symfony Remember Me (Doctrine token provider).
 */
final class Version20260310100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create rememberme_token table for Remember Me functionality';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE rememberme_token (
                series VARCHAR(88) NOT NULL,
                value VARCHAR(88) NOT NULL,
                lastused TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                class VARCHAR(100) DEFAULT \'\' NOT NULL,
                username VARCHAR(200) NOT NULL,
                PRIMARY KEY (series)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS rememberme_token');
    }
}
