<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260310160847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add carrier_id to refresh_token and make user_id nullable to support Carrier authentication';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE geo_area ALTER geometry TYPE geometry(MULTIPOLYGON, 4326)');
        $this->addSql('ALTER TABLE refresh_token ADD carrier_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE refresh_token ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F219521DFC797 FOREIGN KEY (carrier_id) REFERENCES carrier (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_C74F219521DFC797 ON refresh_token (carrier_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA topology');
        $this->addSql('ALTER TABLE geo_area ALTER geometry TYPE geometry(GEOMETRY, 0)');
        $this->addSql('ALTER TABLE refresh_token DROP CONSTRAINT FK_C74F219521DFC797');
        $this->addSql('DROP INDEX IDX_C74F219521DFC797');
        $this->addSql('ALTER TABLE refresh_token DROP carrier_id');
        $this->addSql('ALTER TABLE refresh_token ALTER user_id SET NOT NULL');
    }
}
