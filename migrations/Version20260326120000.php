<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Moves stackable and manipulator_needed from the cargo table to the order table.
 *
 * Migration strategy (up):
 *   1. Add nullable boolean columns to "order".
 *   2. Populate them via OR-aggregation: an order is stackable / needs a manipulator
 *      if at least one of its cargo items had that flag set to TRUE.
 *   3. Create indexes on the new columns.
 *   4. Drop columns (and their auto-created indexes) from cargo.
 *
 * Migration strategy (down):
 *   1. Restore the columns on cargo with a NOT NULL DEFAULT FALSE.
 *   2. Copy the flag value from the parent order back to every cargo row
 *      (best-effort reversal – exact per-cargo values are lost after up()).
 *   3. Drop indexes and columns from "order".
 */
final class Version20260326120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move stackable and manipulator_needed from cargo to order';
    }

    public function up(Schema $schema): void
    {
        // 1. Add nullable columns to order (safe – no NOT NULL constraint yet)
        $this->addSql('ALTER TABLE "order" ADD stackable BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD manipulator_needed BOOLEAN DEFAULT NULL');

        // 2. Populate: true when at least one related cargo row had the flag true
        $this->addSql(<<<'SQL'
            UPDATE "order" o
            SET stackable = EXISTS (
                SELECT 1 FROM cargo c
                WHERE c.related_order_id = o.id
                  AND c.stackable = TRUE
            )
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE "order" o
            SET manipulator_needed = EXISTS (
                SELECT 1 FROM cargo c
                WHERE c.related_order_id = o.id
                  AND c.manipulator_needed = TRUE
            )
        SQL);

        // 3. Create indexes on the order table
        $this->addSql('CREATE INDEX idx_order_stackable ON "order" (stackable)');
        $this->addSql('CREATE INDEX idx_order_manipulator_needed ON "order" (manipulator_needed)');

        // 4. Drop columns from cargo (PostgreSQL automatically drops associated indexes)
        $this->addSql('ALTER TABLE cargo DROP COLUMN stackable');
        $this->addSql('ALTER TABLE cargo DROP COLUMN manipulator_needed');
    }

    public function down(Schema $schema): void
    {
        // 1. Restore columns on cargo
        $this->addSql('ALTER TABLE cargo ADD stackable BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE cargo ADD manipulator_needed BOOLEAN NOT NULL DEFAULT FALSE');

        // 2. Copy values back from order (best-effort – per-cargo granularity is lost)
        $this->addSql(<<<'SQL'
            UPDATE cargo c
            SET stackable         = o.stackable,
                manipulator_needed = o.manipulator_needed
            FROM "order" o
            WHERE c.related_order_id = o.id
              AND o.stackable IS NOT NULL
              AND o.manipulator_needed IS NOT NULL
        SQL);

        // 3. Remove indexes and columns from order
        $this->addSql('DROP INDEX IF EXISTS idx_order_stackable');
        $this->addSql('DROP INDEX IF EXISTS idx_order_manipulator_needed');
        $this->addSql('ALTER TABLE "order" DROP COLUMN stackable');
        $this->addSql('ALTER TABLE "order" DROP COLUMN manipulator_needed');
    }
}
