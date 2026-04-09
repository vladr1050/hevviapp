<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Invoice;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Loads invoice PDF bytes from storage when a rule requires an attachment.
 *
 * @phpstan-type Attachment array{filename: string, binary: string}
 */
final class NotificationAttachmentResolver
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/invoices')]
        private readonly string $invoiceStorageDir,
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
        $fullPath = $this->invoiceStorageDir.'/'.$relative;
        if (!is_readable($fullPath)) {
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
}
