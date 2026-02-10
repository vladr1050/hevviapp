<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203214101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_order_pickup_address ON "order" (pickup_address)');
        $this->addSql('CREATE INDEX idx_order_dropout_address ON "order" (dropout_address)');
        $this->addSql('ALTER INDEX idx_f52993987b00651c RENAME TO idx_order_status');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_order_pickup_address');
        $this->addSql('DROP INDEX idx_order_dropout_address');
        $this->addSql('ALTER INDEX idx_order_status RENAME TO idx_f52993987b00651c');
    }
}
