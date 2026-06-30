<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add manual offer price adjustment metadata to order_offer.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE order_offer ADD pricing_source VARCHAR(32) DEFAULT 'calculated' NOT NULL");
        $this->addSql('ALTER TABLE order_offer ADD adjustment_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_offer ADD adjusted_by_manager_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE order_offer ADD superseded_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE order_offer ADD CONSTRAINT FK_ORDER_OFFER_ADJUSTED_BY FOREIGN KEY (adjusted_by_manager_id) REFERENCES manager (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_offer ADD CONSTRAINT FK_ORDER_OFFER_SUPERSEDED_BY FOREIGN KEY (superseded_by_id) REFERENCES order_offer (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ORDER_OFFER_SUPERSEDED_BY ON order_offer (superseded_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_offer DROP CONSTRAINT FK_ORDER_OFFER_SUPERSEDED_BY');
        $this->addSql('ALTER TABLE order_offer DROP CONSTRAINT FK_ORDER_OFFER_ADJUSTED_BY');
        $this->addSql('DROP INDEX UNIQ_ORDER_OFFER_SUPERSEDED_BY');
        $this->addSql('ALTER TABLE order_offer DROP superseded_by_id');
        $this->addSql('ALTER TABLE order_offer DROP adjusted_by_manager_id');
        $this->addSql('ALTER TABLE order_offer DROP adjustment_reason');
        $this->addSql('ALTER TABLE order_offer DROP pricing_source');
    }
}
