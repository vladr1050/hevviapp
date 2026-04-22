<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Controller\Admin;

use App\Repository\InvoiceRepository;
use App\Service\Document\StoredPdfPathResolver;
use App\Repository\OrderAttachmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Отдаёт вложения заказов для пользователей admin-панели.
 *
 * Находится под /admin — обрабатывается admin firewall (session auth).
 * Путь до файла на диске клиенту никогда не раскрывается.
 *
 * PDF счетов: /admin/files/invoice/{id} — только по UUID из БД; файл в document storage (см. StoredPdfPathResolver).
 */
#[Route('/admin/files', name: 'admin_file_')]
#[IsGranted('ROLE_ADMIN')]
class AdminFileController extends AbstractController
{
    public function __construct(
        private readonly OrderAttachmentRepository $attachmentRepository,
        private readonly InvoiceRepository $invoiceRepository,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
        private readonly StoredPdfPathResolver $storedPdfPathResolver,
    ) {
    }

    #[Route('/{salt}', name: 'download', methods: ['GET'], requirements: ['salt' => '[0-9a-f]{64}'])]
    public function download(string $salt): StreamedResponse
    {
        $attachment = $this->attachmentRepository->findOneBySalt($salt);

        if (!$attachment) {
            throw $this->createNotFoundException('File not found.');
        }

        $absolutePath = $this->publicDir . '/' . $attachment->getFilePath();

        if (!is_file($absolutePath)) {
            throw $this->createNotFoundException('File not found on disk.');
        }

        $originalName = $attachment->getOriginalName();

        return new StreamedResponse(
            static function () use ($absolutePath): void {
                $gz = gzopen($absolutePath, 'rb');
                while (!gzeof($gz)) {
                    echo gzread($gz, 65536);
                }
                gzclose($gz);
            },
            200,
            [
                'Content-Type'           => 'application/pdf',
                'Content-Disposition'    => sprintf('inline; filename="%s"', addslashes($originalName)),
                'Cache-Control'          => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    #[Route('/invoice/{id}', name: 'invoice_pdf', methods: ['GET'], requirements: ['id' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}'])]
    public function invoicePdf(string $id): BinaryFileResponse
    {
        $invoice = $this->invoiceRepository->find($id);
        $relative = $invoice?->getPdfRelativePath();
        if ($relative === null || $relative === '') {
            throw $this->createNotFoundException('Invoice PDF not available.');
        }

        $fileReal = $this->storedPdfPathResolver->resolveReadableFile($relative);
        if ($fileReal === null || !$this->storedPdfPathResolver->isAllowedAbsolutePath($fileReal)) {
            throw $this->createNotFoundException('Invoice PDF not found on disk.');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) $invoice->getInvoiceNumber()) . '.pdf';

        $response = new BinaryFileResponse($fileReal);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $safeName);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
