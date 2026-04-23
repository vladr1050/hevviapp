<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Carrier profile: optional VAT rate % for post-delivery freight invoice PDF.
 */
final class Version20260424120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable carrier.vat_rate (percent) for carrier invoice VAT after delivery.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE carrier ADD vat_rate NUMERIC(7, 4) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE carrier DROP COLUMN vat_rate');
    }
}
