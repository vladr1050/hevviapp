<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add carrier_eta_at override for delivery countdown / Change ETA.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" ADD carrier_eta_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP carrier_eta_at');
    }
}
