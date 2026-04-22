<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Align documents.* index names with Doctrine ORM naming (fixes schema:validate / dump-sql renames only).
 */
final class Version20260422100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename documents secondary indexes to names expected by Doctrine schema tool.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_a2b07288d9f6d38 RENAME TO IDX_A2B072888D9F6D38');
        $this->addSql('ALTER INDEX idx_a2b07288f6a2dc68 RENAME TO IDX_A2B072888D406B3');
        $this->addSql('ALTER INDEX idx_a2b07288e47d71 RENAME TO IDX_A2B07288C31F441B');
        $this->addSql('ALTER INDEX idx_a2b07288b94ae689 RENAME TO IDX_A2B07288D43F1C1F');
        $this->addSql('ALTER INDEX idx_a2b07288b6d5d29 RENAME TO IDX_A2B07288E78E678');
        $this->addSql('ALTER INDEX idx_a2b07288a6df1c RENAME TO IDX_A2B072882B6ADBBA');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX IDX_A2B072882B6ADBBA RENAME TO IDX_A2B07288A6DF1C');
        $this->addSql('ALTER INDEX IDX_A2B07288E78E678 RENAME TO IDX_A2B07288B6D5D29');
        $this->addSql('ALTER INDEX IDX_A2B07288D43F1C1F RENAME TO IDX_A2B07288B94AE689');
        $this->addSql('ALTER INDEX IDX_A2B07288C31F441B RENAME TO IDX_A2B07288E47D71');
        $this->addSql('ALTER INDEX IDX_A2B072888D406B3 RENAME TO IDX_A2B07288F6A2DC68');
        $this->addSql('ALTER INDEX IDX_A2B072888D9F6D38 RENAME TO IDX_A2B07288D9F6D38');
    }
}
