<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-carrier pricing: algorithm, default carrier flag, price coefficient; service area carrier/country/home zone; global coefficient in app_settings.
 */
final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Carrier pricing algorithm, coefficients; ServiceArea carrier/country/home zone; AppSettings default price coefficient.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE carrier ADD is_default_for_pricing BOOLEAN DEFAULT false NOT NULL");
        $this->addSql("ALTER TABLE carrier ADD pricing_algorithm VARCHAR(32) DEFAULT 'flat_by_drop_off_zone' NOT NULL");
        $this->addSql('ALTER TABLE carrier ADD price_coefficient NUMERIC(8, 4) DEFAULT NULL');

        $this->addSql('ALTER TABLE service_area ADD carrier_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE service_area ADD country VARCHAR(2) DEFAULT \'LV\' NOT NULL');
        $this->addSql('ALTER TABLE service_area ADD is_home_zone BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE service_area ADD CONSTRAINT FK_SERVICE_AREA_CARRIER FOREIGN KEY (carrier_id) REFERENCES carrier (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_SERVICE_AREA_CARRIER ON service_area (carrier_id)');

        $this->addSql('ALTER TABLE app_settings ADD default_price_coefficient NUMERIC(8, 4) DEFAULT \'1.0000\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_settings DROP default_price_coefficient');
        $this->addSql('ALTER TABLE service_area DROP CONSTRAINT FK_SERVICE_AREA_CARRIER');
        $this->addSql('DROP INDEX IDX_SERVICE_AREA_CARRIER');
        $this->addSql('ALTER TABLE service_area DROP carrier_id');
        $this->addSql('ALTER TABLE service_area DROP country');
        $this->addSql('ALTER TABLE service_area DROP is_home_zone');
        $this->addSql('ALTER TABLE carrier DROP is_default_for_pricing');
        $this->addSql('ALTER TABLE carrier DROP pricing_algorithm');
        $this->addSql('ALTER TABLE carrier DROP price_coefficient');
    }
}
