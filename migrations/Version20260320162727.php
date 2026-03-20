<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320162727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE order_attachment (created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, salt VARCHAR(128) NOT NULL, file_path VARCHAR(512) NOT NULL, original_name VARCHAR(255) NOT NULL, file_size BIGINT NOT NULL, related_order_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CA8524EE8FFBE0F7 ON order_attachment (salt)');
        $this->addSql('CREATE INDEX IDX_CA8524EE2B1C2395 ON order_attachment (related_order_id)');
        $this->addSql('CREATE INDEX idx_order_attachment_salt ON order_attachment (salt)');
        $this->addSql('ALTER TABLE order_attachment ADD CONSTRAINT FK_CA8524EE2B1C2395 FOREIGN KEY (related_order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE geo_area ALTER geometry TYPE geometry(MULTIPOLYGON, 4326)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA topology');
        $this->addSql('ALTER TABLE order_attachment DROP CONSTRAINT FK_CA8524EE2B1C2395');
        $this->addSql('DROP TABLE order_attachment');
        $this->addSql('ALTER TABLE geo_area ALTER geometry TYPE geometry(GEOMETRY, 0)');
    }
}
