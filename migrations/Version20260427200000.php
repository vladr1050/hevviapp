<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Audit log for portal JWT login with terms acceptance (sender/carrier audience).
 */
final class Version20260427200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add portal_login_consent_log for login + published terms acceptance audit.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_login_consent_log (id UUID NOT NULL, email VARCHAR(255) NOT NULL, account_type VARCHAR(20) NOT NULL, portal_audience VARCHAR(20) NOT NULL, subject_id UUID DEFAULT NULL, terms_version INT DEFAULT NULL, ip_address VARCHAR(45) NOT NULL, user_agent TEXT DEFAULT NULL, terms_of_use_revision_id UUID DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_portal_login_consent_created ON portal_login_consent_log (created_at)');
        $this->addSql('CREATE INDEX idx_portal_login_consent_email ON portal_login_consent_log (email)');
        $this->addSql('ALTER TABLE portal_login_consent_log ADD CONSTRAINT FK_portal_login_terms FOREIGN KEY (terms_of_use_revision_id) REFERENCES terms_of_use_revision (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_login_consent_log DROP CONSTRAINT FK_portal_login_terms');
        $this->addSql('DROP TABLE portal_login_consent_log');
    }
}
