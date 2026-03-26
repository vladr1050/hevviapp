<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds cancel_reason column to the order table.
 *
 * The field stores a human-readable reason when an order is cancelled by the sender.
 * It is optional (nullable) so that orders cancelled through other flows (e.g. admin,
 * carrier) are not forced to carry a reason.
 */
final class Version20260326130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable cancel_reason (VARCHAR 255) to the order table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" ADD cancel_reason VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP COLUMN cancel_reason');
    }
}
