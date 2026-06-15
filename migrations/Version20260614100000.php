<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260614100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Waiting list applicants, nullable notification_log.related_order_id, default notification rules.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE waiting_list_applicant (
    id UUID NOT NULL,
    email VARCHAR(255) NOT NULL,
    type VARCHAR(16) NOT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_waiting_list_applicant_email ON waiting_list_applicant (email)');

        $this->addSql('ALTER TABLE notification_log ALTER related_order_id DROP NOT NULL');

        $confirmationSubject = 'Hevvi — pieteikums saņemts';
        $confirmationBody = <<<'HTML'
<p>Paldies!</p>
<p>Jūsu pieteikums kā <strong>{{APPLICANT_TYPE}}</strong> ar e-pastu <strong>{{APPLICANT_EMAIL}}</strong> ir saņemts.</p>
<p>Mēs sazināsimies ar jums <strong>1 darba dienas</strong> laikā.</p>
<p>Jūs arī varat sazināties ar mums:<br>
Tālrunis: {{OPERATOR_PHONE}}<br>
E-pasts: {{OPERATOR_EMAIL}}</p>
HTML;

        $operatorSubject = 'Jauns waiting-list pieteikums ({{APPLICANT_TYPE}})';
        $operatorBody = <<<'HTML'
<p>Saņemts jauns pieteikums gaidīšanas sarakstā.</p>
<p>E-pasts: <strong>{{APPLICANT_EMAIL}}</strong><br>
Tips: <strong>{{APPLICANT_TYPE}}</strong></p>
HTML;

        $this->insertRuleIfMissing(
            'Waiting list — confirmation to applicant',
            'WAITING_LIST_CONFIRMATION',
            'applicant',
            $confirmationSubject,
            $confirmationBody,
        );
        $this->insertRuleIfMissing(
            'Waiting list — new application to operator',
            'WAITING_LIST_NEW_APPLICATION',
            'operator',
            $operatorSubject,
            $operatorBody,
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM notification_rule WHERE event_key IN ('WAITING_LIST_CONFIRMATION', 'WAITING_LIST_NEW_APPLICATION')");
        $this->addSql('DROP TABLE IF EXISTS waiting_list_applicant');
        // Rows without order would block NOT NULL restore; leave nullable on down.
    }

    private function insertRuleIfMissing(
        string $name,
        string $eventKey,
        string $recipientType,
        string $subject,
        string $body,
    ): void {
        $escapedName = str_replace("'", "''", $name);
        $escapedSubject = str_replace("'", "''", $subject);
        $escapedBody = str_replace("'", "''", $body);

        $this->addSql(<<<SQL
INSERT INTO notification_rule (
    id, name, description, event_key, recipient_type,
    subject_template, body_template, attach_invoice_pdf, is_active, send_once_per_order,
    created_at, updated_at
)
SELECT
    gen_random_uuid(),
    '{$escapedName}',
    NULL,
    '{$eventKey}',
    '{$recipientType}',
    '{$escapedSubject}',
    '{$escapedBody}',
    false,
    true,
    false,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM notification_rule WHERE event_key = '{$eventKey}' AND recipient_type = '{$recipientType}'
)
SQL);
    }
}
