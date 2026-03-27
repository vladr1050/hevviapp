<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds a human-readable order_number to the order table.
 *
 * Strategy:
 *   1. Create a PostgreSQL SEQUENCE for future orders.
 *   2. Add a nullable unique INT column order_number.
 *   3. Backfill existing orders in chronological order (oldest → lowest number).
 *   4. Advance the sequence past the highest assigned value so new orders
 *      continue seamlessly after the backfilled ones.
 */
final class Version20260326140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order_number (INT UNIQUE) + PostgreSQL SEQUENCE; backfill existing orders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE order_number_seq START 1 INCREMENT 1 CACHE 1');

        $this->addSql('ALTER TABLE "order" ADD order_number INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_order_order_number ON "order" (order_number)');

        // Присваиваем существующим заказам номера в хронологическом порядке.
        // ROW_NUMBER гарантирует детерминированный порядок; самый ранний заказ
        // получает наименьший номер.
        $this->addSql(<<<'SQL'
            WITH ordered AS (
                SELECT id, ROW_NUMBER() OVER (ORDER BY created_at ASC) AS rn
                FROM "order"
            )
            UPDATE "order"
            SET order_number = ordered.rn
            FROM ordered
            WHERE "order".id = ordered.id
        SQL);

        // Продвигаем последовательность за максимально присвоенное значение,
        // чтобы новые заказы продолжали ряд без коллизий.
        $this->addSql(<<<'SQL'
            SELECT setval(
                'order_number_seq',
                COALESCE((SELECT MAX(order_number) FROM "order"), 0) + 1,
                false
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS uniq_order_order_number');
        $this->addSql('ALTER TABLE "order" DROP COLUMN order_number');
        $this->addSql('DROP SEQUENCE IF EXISTS order_number_seq');
    }
}
