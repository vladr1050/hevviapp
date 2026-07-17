<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_test flags to user, carrier, and order for Live/Test separation in admin.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD is_test BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE INDEX idx_user_is_test ON "user" (is_test)');

        $this->addSql('ALTER TABLE carrier ADD is_test BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE INDEX idx_carrier_is_test ON carrier (is_test)');

        $this->addSql('ALTER TABLE "order" ADD is_test BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('CREATE INDEX idx_order_is_test ON "order" (is_test)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_order_is_test');
        $this->addSql('ALTER TABLE "order" DROP is_test');

        $this->addSql('DROP INDEX idx_carrier_is_test');
        $this->addSql('ALTER TABLE carrier DROP is_test');

        $this->addSql('DROP INDEX idx_user_is_test');
        $this->addSql('ALTER TABLE "user" DROP is_test');
    }
}
