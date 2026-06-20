<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-sender local price coefficient (same semantics as carrier).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD price_coefficient NUMERIC(8, 4) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP price_coefficient');
    }
}
