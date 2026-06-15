<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Oversized cargo pricing: pallet-place → fixed chargeable weight table.
 *
 * The seeded rows are EXAMPLE values so the feature is usable out of the box;
 * adjust them in the admin (Settings → Oversized weight tiers) to real numbers.
 */
final class Version20260615120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Oversized weight tiers table (pallet count → fixed pricing weight) with example rows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE oversized_weight_tier (
    id UUID NOT NULL,
    pallets INT NOT NULL,
    weight_kg INT NOT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_oversized_weight_tier_pallets ON oversized_weight_tier (pallets)');

        // Example tiers: 1..12 pallet places, 500 kg per place. Replace with real data.
        for ($pallets = 1; $pallets <= 12; ++$pallets) {
            $weightKg = $pallets * 500;
            $this->addSql(
                "INSERT INTO oversized_weight_tier (id, pallets, weight_kg, created_at, updated_at) "
                . "VALUES (gen_random_uuid(), {$pallets}, {$weightKg}, NOW(), NOW())"
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS oversized_weight_tier');
    }
}
