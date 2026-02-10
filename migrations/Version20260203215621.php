<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203215621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_user_name ON "user" (first_name, last_name)');
        $this->addSql('CREATE INDEX idx_user_first_name ON "user" (first_name)');
        $this->addSql('CREATE INDEX idx_user_last_name ON "user" (last_name)');
        $this->addSql('ALTER INDEX idx_8d93d649a393d2fb RENAME TO idx_user_state');
        $this->addSql('ALTER INDEX idx_8d93d6494180c698 RENAME TO idx_user_locale');
        $this->addSql('ALTER INDEX idx_8d93d649444f97dd RENAME TO idx_user_phone');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_user_name');
        $this->addSql('DROP INDEX idx_user_first_name');
        $this->addSql('DROP INDEX idx_user_last_name');
        $this->addSql('ALTER INDEX idx_user_state RENAME TO idx_8d93d649a393d2fb');
        $this->addSql('ALTER INDEX idx_user_locale RENAME TO idx_8d93d6494180c698');
        $this->addSql('ALTER INDEX idx_user_phone RENAME TO idx_8d93d649444f97dd');
    }
}
