<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Normalize geo_area.geometry typmod so Doctrine schema tool matches PostGIS mapping.
 */
final class Version20260422110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align geo_area.geometry with geometry(MULTIPOLYGON,4326) for ORM / jsor/doctrine-postgis.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE geo_area ALTER geometry TYPE geometry(MULTIPOLYGON, 4326)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE geo_area ALTER geometry TYPE geometry(GEOMETRY, 0)');
    }
}
