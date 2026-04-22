<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Order documents registry + optional vehicle plate on order (admin until carrier UI).
 */
final class Version20260421180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create documents table and add order.vehicle_plate.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE documents (
    id UUID NOT NULL,
    order_id UUID NOT NULL,
    document_type VARCHAR(32) NOT NULL,
    document_number VARCHAR(64) NOT NULL,
    related_document_id UUID DEFAULT NULL,
    sender_company_id UUID DEFAULT NULL,
    receiver_company_id UUID DEFAULT NULL,
    carrier_company_id UUID DEFAULT NULL,
    file_path VARCHAR(1024) DEFAULT NULL,
    amount_net INT DEFAULT NULL,
    amount_vat INT DEFAULT NULL,
    amount_total INT DEFAULT NULL,
    issued_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
    status VARCHAR(16) NOT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_documents_order_type ON documents (order_id, document_type)');
        $this->addSql('CREATE UNIQUE INDEX uniq_documents_document_number ON documents (document_number)');
        $this->addSql('CREATE INDEX IDX_A2B07288D9F6D38 ON documents (order_id)');
        $this->addSql('CREATE INDEX IDX_A2B07288F6A2DC68 ON documents (related_document_id)');
        $this->addSql('CREATE INDEX IDX_A2B07288E47D71 ON documents (sender_company_id)');
        $this->addSql('CREATE INDEX IDX_A2B07288B94AE689 ON documents (receiver_company_id)');
        $this->addSql('CREATE INDEX IDX_A2B07288B6D5D29 ON documents (carrier_company_id)');
        $this->addSql('CREATE INDEX IDX_A2B07288A6DF1C ON documents (document_type)');
        $this->addSql('CREATE INDEX IDX_A2B072887B00651C ON documents (status)');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B07288D9F6D38 FOREIGN KEY (order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B07288F6A2DC68 FOREIGN KEY (related_document_id) REFERENCES documents (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B07288E47D71 FOREIGN KEY (sender_company_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B07288B94AE689 FOREIGN KEY (receiver_company_id) REFERENCES billing_company (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B07288B6D5D29 FOREIGN KEY (carrier_company_id) REFERENCES carrier (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "order" ADD vehicle_plate VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP COLUMN vehicle_plate');
        $this->addSql('DROP TABLE documents');
    }
}
