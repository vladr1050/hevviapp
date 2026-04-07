<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invoice (per accepted OrderOffer), atomic daily sequence, PDF path.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE invoice_day_counter (
    day DATE NOT NULL,
    last_seq INT NOT NULL,
    PRIMARY KEY(day)
)
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE invoice (
    id UUID NOT NULL,
    invoice_number VARCHAR(32) NOT NULL,
    status SMALLINT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    currency VARCHAR(3) NOT NULL,
    amount_freight INT NOT NULL,
    amount_commission INT NOT NULL,
    amount_subtotal INT NOT NULL,
    amount_vat INT NOT NULL,
    amount_gross INT NOT NULL,
    vat_rate_percent NUMERIC(7, 4) NOT NULL,
    fee_percent NUMERIC(7, 4) NOT NULL,
    seller_name VARCHAR(255) NOT NULL,
    seller_registration_number VARCHAR(255) NOT NULL,
    seller_vat_number VARCHAR(64) DEFAULT NULL,
    seller_address_line1 VARCHAR(512) NOT NULL,
    seller_address_line2 VARCHAR(512) DEFAULT NULL,
    seller_email VARCHAR(255) NOT NULL,
    seller_phone VARCHAR(64) NOT NULL,
    buyer_company_name VARCHAR(255) NOT NULL,
    buyer_registration_number VARCHAR(255) NOT NULL,
    buyer_vat_number VARCHAR(64) DEFAULT NULL,
    buyer_address TEXT NOT NULL,
    buyer_email_snapshot VARCHAR(255) DEFAULT NULL,
    order_reference VARCHAR(64) NOT NULL,
    pickup_address VARCHAR(512) NOT NULL,
    delivery_address VARCHAR(512) NOT NULL,
    pdf_relative_path VARCHAR(1024) DEFAULT NULL,
    pdf_error TEXT DEFAULT NULL,
    email_error TEXT DEFAULT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    order_offer_id UUID NOT NULL,
    related_order_id UUID NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_INVOICE_NUMBER ON invoice (invoice_number)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_INVOICE_ORDER_OFFER ON invoice (order_offer_id)');
        $this->addSql('CREATE INDEX IDX_INVOICE_ORDER ON invoice (related_order_id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_INVOICE_ORDER_OFFER FOREIGN KEY (order_offer_id) REFERENCES order_offer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_INVOICE_ORDER FOREIGN KEY (related_order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_day_counter');
    }
}
