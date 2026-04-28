<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Notification rules: optional list of order document PDF types to attach (multi-PDF emails).
 */
final class Version20260428140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification_rule.attach_document_types (JSON); seed delivered-document rules.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_rule ADD attach_document_types JSON DEFAULT NULL');
        $this->addSql("UPDATE notification_rule SET attach_document_types = '[\"CUSTOMER_INVOICE\",\"CARRIER_INVOICE\"]'::json WHERE event_key = 'ORDER_DELIVERED_SENDER_DOCUMENT'");
        $this->addSql("UPDATE notification_rule SET attach_document_types = '[\"CARRIER_INVOICE\"]'::json WHERE event_key = 'ORDER_DELIVERED_CARRIER_DOCUMENT'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification_rule DROP attach_document_types');
    }
}
