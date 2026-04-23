<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Versioned Terms and Conditions (HTML) for carrier and sender portals, editable in Sonata.
 */
final class Version20260425100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add terms_of_use_revision for portal Terms & Conditions (carrier/sender, versioned HTML).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE terms_of_use_revision (id UUID NOT NULL, audience VARCHAR(20) NOT NULL, version INT NOT NULL, title VARCHAR(512) NOT NULL, subtitle VARCHAR(512) DEFAULT NULL, body_html TEXT NOT NULL, status VARCHAR(20) NOT NULL, published_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX terms_audience_version_uniq ON terms_of_use_revision (audience, version)');
        $this->addSql('CREATE INDEX IDX_terms_audience_status ON terms_of_use_revision (audience, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE terms_of_use_revision');
    }
}
