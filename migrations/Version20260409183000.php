<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification rules and notification logs (Hevvi Notification Service MVP).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE notification_rule (
    id UUID NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    event_key VARCHAR(64) NOT NULL,
    recipient_type VARCHAR(32) NOT NULL,
    subject_template TEXT NOT NULL,
    body_template TEXT NOT NULL,
    attach_invoice_pdf BOOLEAN NOT NULL DEFAULT false,
    is_active BOOLEAN NOT NULL DEFAULT true,
    send_once_per_order BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX idx_notification_rule_event_key ON notification_rule (event_key)');

        $this->addSql(<<<'SQL'
CREATE TABLE notification_log (
    id UUID NOT NULL,
    related_order_id UUID NOT NULL,
    notification_rule_id UUID DEFAULT NULL,
    event_key VARCHAR(64) NOT NULL,
    recipient_type VARCHAR(32) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject_rendered TEXT NOT NULL,
    body_rendered TEXT NOT NULL,
    attachment_type VARCHAR(32) DEFAULT NULL,
    status VARCHAR(16) NOT NULL,
    error_message TEXT DEFAULT NULL,
    provider_message_id VARCHAR(255) DEFAULT NULL,
    sent_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX idx_notification_log_order ON notification_log (related_order_id)');
        $this->addSql('CREATE INDEX idx_notification_log_rule_order_event ON notification_log (notification_rule_id, related_order_id, event_key, status)');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT FK_NOTIFICATION_LOG_ORDER FOREIGN KEY (related_order_id) REFERENCES "order" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT FK_NOTIFICATION_LOG_RULE FOREIGN KEY (notification_rule_id) REFERENCES notification_rule (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_log');
        $this->addSql('DROP TABLE notification_rule');
    }
}
