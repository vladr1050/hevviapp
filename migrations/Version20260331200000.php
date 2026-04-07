<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional vat_number to user and carrier.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD vat_number VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE carrier ADD vat_number VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN vat_number');
        $this->addSql('ALTER TABLE carrier DROP COLUMN vat_number');
    }
}
