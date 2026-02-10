<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260203191129 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'user index';
    }

    public function up(Schema $schema): void
    {
        // Если еще не jsonb — конвертируем
        $this->addSql('
            ALTER TABLE "user"
            ALTER COLUMN roles TYPE jsonb
            USING roles::jsonb
        ');

        // Создаем индекс
        $this->addSql('
            CREATE INDEX idx_user_roles_gin
            ON "user"
            USING GIN (roles jsonb_path_ops)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_user_roles_gin');

        $this->addSql('
            ALTER TABLE "user"
            ALTER COLUMN roles TYPE json
            USING roles::json
        ');
    }
}
