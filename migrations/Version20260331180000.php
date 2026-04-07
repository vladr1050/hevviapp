<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Billing companies (invoice issuer settings, requisites, VAT rate, payment terms).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE billing_company (
    id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    registration_number VARCHAR(255) NOT NULL,
    vat_number VARCHAR(64) DEFAULT NULL,
    vat_rate NUMERIC(7, 4) NOT NULL,
    address_street VARCHAR(255) NOT NULL,
    address_number VARCHAR(64) NOT NULL,
    address_city VARCHAR(255) NOT NULL,
    address_country VARCHAR(255) NOT NULL,
    address_postal_code VARCHAR(32) NOT NULL,
    iban VARCHAR(64) NOT NULL,
    phone VARCHAR(64) NOT NULL,
    email VARCHAR(255) NOT NULL,
    representative VARCHAR(255) DEFAULT NULL,
    payment_due_days INT DEFAULT NULL,
    issues_invoices BOOLEAN DEFAULT false NOT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);

        $this->addSql('CREATE INDEX IDX_BILLING_COMPANY_ISSUES ON billing_company (issues_invoices)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE billing_company');
    }
}
