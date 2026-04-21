<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional iban and bank_account_holder to user (sender) and carrier for payouts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD iban VARCHAR(34) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD bank_account_holder VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE carrier ADD iban VARCHAR(34) DEFAULT NULL');
        $this->addSql('ALTER TABLE carrier ADD bank_account_holder VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN iban');
        $this->addSql('ALTER TABLE "user" DROP COLUMN bank_account_holder');
        $this->addSql('ALTER TABLE carrier DROP COLUMN iban');
        $this->addSql('ALTER TABLE carrier DROP COLUMN bank_account_holder');
    }
}
