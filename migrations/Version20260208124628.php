<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208124628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dropout coordinates (latitude, longitude) to Order entity for service area calculation';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE geo_area ALTER geometry TYPE geometry(MULTIPOLYGON, 4326)');
        $this->addSql('ALTER TABLE "order" ADD dropout_latitude NUMERIC(10, 7) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD dropout_longitude NUMERIC(10, 7) DEFAULT NULL');
        
        // Add composite index for faster geospatial queries on Order
        $this->addSql('CREATE INDEX idx_order_dropout_coordinates ON "order" (dropout_latitude, dropout_longitude) WHERE dropout_latitude IS NOT NULL AND dropout_longitude IS NOT NULL');
        
        // Add GIST index on geo_area.geometry for spatial queries (ST_Contains, ST_Intersects, etc.)
        // GIST (Generalized Search Tree) is optimized for geometric data types in PostgreSQL
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_geo_area_geometry ON geo_area USING GIST (geometry)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA topology');
        $this->addSql('DROP INDEX IF EXISTS idx_order_dropout_coordinates');
        $this->addSql('DROP INDEX IF EXISTS idx_geo_area_geometry');
        $this->addSql('ALTER TABLE geo_area ALTER geometry TYPE geometry(GEOMETRY, 0)');
        $this->addSql('ALTER TABLE "order" DROP dropout_latitude');
        $this->addSql('ALTER TABLE "order" DROP dropout_longitude');
    }
}
