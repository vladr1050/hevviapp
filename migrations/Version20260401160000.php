<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Идемпотентно гарантирует наличие order_number_seq.
 * Нужна после рассинхрона или если schema:update удалил sequence (вне ORM).
 */
final class Version20260401160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure order_number_seq exists and setval aligns with max(order_number)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE IF NOT EXISTS order_number_seq START 1 INCREMENT 1 CACHE 1');

        $this->addSql(<<<'SQL'
SELECT setval(
    'order_number_seq',
    COALESCE((SELECT MAX(order_number) FROM "order"), 0) + 1,
    false
)
SQL
        );
    }

    public function down(Schema $schema): void
    {
    }
}
