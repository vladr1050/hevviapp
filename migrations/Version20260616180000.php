<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Waiting list phone field and updated notification templates without applicant type.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE waiting_list_applicant ADD phone VARCHAR(32) NOT NULL DEFAULT ''");
        $this->addSql("UPDATE waiting_list_applicant SET phone = '' WHERE phone IS NULL");

        $confirmationBody = <<<'HTML'
<p>Paldies!</p>
<p>Jūsu pieteikums ar e-pastu <strong>{{APPLICANT_EMAIL}}</strong> ir saņemts.</p>
<p>Mēs sazināsimies ar jums <strong>1 darba dienas</strong> laikā.</p>
<p>Jūs arī varat sazināties ar mums:<br>
Tālrunis: {{OPERATOR_PHONE}}<br>
E-pasts: {{OPERATOR_EMAIL}}</p>
HTML;

        $operatorSubject = 'Jauns waiting-list pieteikums';
        $operatorBody = <<<'HTML'
<p>Saņemts jauns pieteikums gaidīšanas sarakstā.</p>
<p>E-pasts: <strong>{{APPLICANT_EMAIL}}</strong><br>
Tālrunis: <strong>{{APPLICANT_PHONE}}</strong></p>
HTML;

        $this->updateRuleBody('WAITING_LIST_CONFIRMATION', 'applicant', $confirmationBody);
        $this->updateRuleSubjectAndBody(
            'WAITING_LIST_NEW_APPLICATION',
            'operator',
            $operatorSubject,
            $operatorBody,
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE waiting_list_applicant DROP COLUMN phone');

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

        $this->updateRuleBody('WAITING_LIST_CONFIRMATION', 'applicant', $confirmationBody);
        $this->updateRuleSubjectAndBody(
            'WAITING_LIST_NEW_APPLICATION',
            'operator',
            $operatorSubject,
            $operatorBody,
        );
    }

    private function updateRuleBody(string $eventKey, string $recipientType, string $body): void
    {
        $escapedBody = str_replace("'", "''", $body);
        $this->addSql(<<<SQL
UPDATE notification_rule
SET body_template = '{$escapedBody}', updated_at = NOW()
WHERE event_key = '{$eventKey}' AND recipient_type = '{$recipientType}'
SQL);
    }

    private function updateRuleSubjectAndBody(
        string $eventKey,
        string $recipientType,
        string $subject,
        string $body,
    ): void {
        $escapedSubject = str_replace("'", "''", $subject);
        $escapedBody = str_replace("'", "''", $body);
        $this->addSql(<<<SQL
UPDATE notification_rule
SET subject_template = '{$escapedSubject}', body_template = '{$escapedBody}', updated_at = NOW()
WHERE event_key = '{$eventKey}' AND recipient_type = '{$recipientType}'
SQL);
    }
}
