<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

/**
 * Move global default price coefficient from app_settings into a dedicated pricing_settings singleton.
 */
final class Version20260525123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pricing_settings singleton; migrate default_price_coefficient from app_settings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE pricing_settings (
                id UUID NOT NULL,
                default_price_coefficient NUMERIC(8, 4) DEFAULT '1.0000' NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql("COMMENT ON COLUMN pricing_settings.id IS '(DC2Type:uuid)'");

        $newId = Uuid::v4()->toRfc4122();

        $this->addSql(sprintf(<<<'SQL'
            INSERT INTO pricing_settings (id, default_price_coefficient, created_at, updated_at)
            SELECT '%s', COALESCE(default_price_coefficient, '1.0000'), NOW(), NOW()
            FROM app_settings
            LIMIT 1
        SQL, $newId));

        $fallbackId = Uuid::v4()->toRfc4122();
        $this->addSql(sprintf(<<<'SQL'
            INSERT INTO pricing_settings (id, default_price_coefficient, created_at, updated_at)
            SELECT '%s', '1.0000', NOW(), NOW()
            WHERE NOT EXISTS (SELECT 1 FROM pricing_settings)
        SQL, $fallbackId));

        $this->addSql('ALTER TABLE app_settings DROP COLUMN IF EXISTS default_price_coefficient');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE app_settings ADD default_price_coefficient NUMERIC(8, 4) DEFAULT '1.0000' NOT NULL");
        $this->addSql(<<<'SQL'
            UPDATE app_settings
            SET default_price_coefficient = COALESCE((SELECT default_price_coefficient FROM pricing_settings LIMIT 1), '1.0000')
        SQL);
        $this->addSql('DROP TABLE pricing_settings');
    }
}
