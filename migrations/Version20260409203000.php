<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Site-wide map / geocoding restrictions (Sonata → Settings).
 */
final class Version20260409203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_settings for public map and Nominatim restriction configuration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE app_settings (
    id UUID NOT NULL,
    restrict_geographic_search BOOLEAN DEFAULT false NOT NULL,
    nominatim_country_codes VARCHAR(255) DEFAULT NULL,
    bbox_min_latitude DOUBLE PRECISION DEFAULT NULL,
    bbox_max_latitude DOUBLE PRECISION DEFAULT NULL,
    bbox_min_longitude DOUBLE PRECISION DEFAULT NULL,
    bbox_max_longitude DOUBLE PRECISION DEFAULT NULL,
    default_map_latitude DOUBLE PRECISION DEFAULT NULL,
    default_map_longitude DOUBLE PRECISION DEFAULT NULL,
    default_map_zoom INT DEFAULT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_settings');
    }
}
