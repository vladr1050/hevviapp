<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Document;
use App\Entity\Invoice;
use App\Service\Document\StoredPdfPathResolver;

/**
 * Loads invoice PDF bytes from storage when a rule requires an attachment.
 *
 * @phpstan-type Attachment array{filename: string, binary: string}
 */
final class NotificationAttachmentResolver
{
    public function __construct(
        private readonly StoredPdfPathResolver $storedPdfPathResolver,
    ) {
    }

    /**
     * @return Attachment|null
     */
    public function resolveInvoicePdf(?Invoice $invoice): ?array
    {
        if (!$invoice instanceof Invoice) {
            return null;
        }
        $relative = $invoice->getPdfRelativePath();
        if ($relative === null || $relative === '') {
            return null;
        }
        $fullPath = $this->storedPdfPathResolver->resolveReadableFile($relative);
        if ($fullPath === null) {
            return null;
        }
        $binary = file_get_contents($fullPath);
        if ($binary === false || $binary === '') {
            return null;
        }
        $number = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $invoice->getInvoiceNumber()) ?? 'invoice';

        return [
            'filename' => sprintf('rekina-%s.pdf', $number),
            'binary' => $binary,
        ];
    }

    /**
     * @return Attachment|null
     */
    public function resolveDocumentPdf(?Document $document): ?array
    {
        if (!$document instanceof Document) {
            return null;
        }
        $relative = $document->getFilePath();
        if ($relative === null || $relative === '') {
            return null;
        }
        $fullPath = $this->storedPdfPathResolver->resolveReadableFile($relative);
        if ($fullPath === null) {
            return null;
        }
        $binary = file_get_contents($fullPath);
        if ($binary === false || $binary === '') {
            return null;
        }
        $number = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $document->getDocumentNumber()) ?? 'document';

        return [
            'filename' => sprintf('document-%s.pdf', $number),
            'binary' => $binary,
        ];
    }
}
