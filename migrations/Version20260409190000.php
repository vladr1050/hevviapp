<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table for Symfony Messenger Doctrine transport (async notifications in production).
 */
final class Version20260409190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create messenger_messages for doctrine:// Messenger transport (optional; sync:// does not use it).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS messenger_messages (
    id BIGSERIAL NOT NULL,
    body TEXT NOT NULL,
    headers TEXT NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    PRIMARY KEY (id)
)
SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS messenger_messages_queue_idx ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
    }
}
