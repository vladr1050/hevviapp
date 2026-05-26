<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reclassify legacy Latvia "cities" (former districts from old GADM import, sized ~1500-3700 km²)
 * as scope=6 DISTRICT so that the freshly imported real OSM valstspilsētas can live under scope=2.
 *
 * Detection is based on area: real valstspilsētas in Latvia are well under 400 km².
 */
final class Version20260526160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move legacy oversized LVA "cities" (scope=2) into a new DISTRICT scope (=6).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE geo_area
            SET scope = 6
            WHERE country_iso3 = 'LVA'
              AND scope = 2
              AND ST_Area(geometry::geography) > 500000000
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE geo_area SET scope = 2 WHERE country_iso3 = 'LVA' AND scope = 6");
    }
}
