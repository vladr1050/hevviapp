<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional shipper and consignee contact fields on order.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" ADD shipper_company_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD shipper_phone VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD shipper_contact_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD consignee_company_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD consignee_phone VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "order" ADD consignee_contact_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "order" DROP consignee_contact_name');
        $this->addSql('ALTER TABLE "order" DROP consignee_phone');
        $this->addSql('ALTER TABLE "order" DROP consignee_company_name');
        $this->addSql('ALTER TABLE "order" DROP shipper_contact_name');
        $this->addSql('ALTER TABLE "order" DROP shipper_phone');
        $this->addSql('ALTER TABLE "order" DROP shipper_company_name');
    }
}
