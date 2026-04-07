<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional platform_fee_percent to billing_company (commission on base freight).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing_company ADD platform_fee_percent NUMERIC(7, 4) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billing_company DROP platform_fee_percent');
    }
}
