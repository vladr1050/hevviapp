<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Force geometry typmod + SRID so introspection matches Jsor PostGIS mapping (MULTIPOLYGON, 4326).
 */
final class Version20260422130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize geo_area.geometry with SRID and MULTIPOLYGON typmod for Doctrine schema tool.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE geo_area
    ALTER COLUMN geometry TYPE geometry(MULTIPOLYGON, 4326)
    USING (
        CASE
            WHEN geometry IS NULL THEN NULL
            ELSE ST_SetSRID(ST_Multi(geometry::geometry), 4326)::geometry(MULTIPOLYGON, 4326)
        END
    )
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE geo_area ALTER geometry TYPE geometry(GEOMETRY, 0) USING geometry::geometry(GEOMETRY,0)');
    }
}
