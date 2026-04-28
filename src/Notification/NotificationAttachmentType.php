<?php

declare(strict_types=1);

namespace App\Notification;

final class NotificationAttachmentType
{
    public const INVOICE_PDF = 'invoice_pdf';

    public const DOCUMENT_PDF = 'document_pdf';

    /** One email with multiple order {@see \App\Entity\Document} PDFs (see notification_rule.attach_document_types). */
    public const DOCUMENTS_PDF = 'documents_pdf';
}
