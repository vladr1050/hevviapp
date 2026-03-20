<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Controller;

use App\Entity\Carrier;
use App\Entity\User;
use App\Repository\OrderAttachmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Отдаёт вложения заказов по уникальному salt.
 * Путь до файла на диске клиенту никогда не раскрывается.
 *
 * Защита: маршрут доступен только аутентифицированным пользователям
 * (main firewall, JWT), контроллер дополнительно проверяет владение заказом.
 */
#[Route('/files', name: 'file_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class FileController extends AbstractController
{
    public function __construct(
        private readonly OrderAttachmentRepository $attachmentRepository,
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    #[Route('/{salt}', name: 'download', methods: ['GET'], requirements: ['salt' => '[0-9a-f]{64}'])]
    public function download(string $salt): StreamedResponse
    {
        $attachment = $this->attachmentRepository->findOneBySalt($salt);

        if (!$attachment) {
            throw $this->createNotFoundException('File not found.');
        }

        $order = $attachment->getRelatedOrder();
        $user  = $this->getUser();

        $hasAccess = match (true) {
            $user instanceof User    => $order?->getSender() === $user,
            $user instanceof Carrier => $order?->getCarrier() === $user,
            default                  => false,
        };

        if (!$hasAccess) {
            throw $this->createAccessDeniedException('You do not have access to this file.');
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
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('inline; filename="%s"', addslashes($originalName)),
                'Cache-Control'       => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
