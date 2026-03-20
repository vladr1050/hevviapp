<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\OrderRepository;
use App\Service\OrderAttachmentUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API для загрузки PDF-вложений к заказу.
 *
 * Endpoint: POST /api/orders/{id}/attachments
 * Content-Type: multipart/form-data
 * Field: files[] (один или несколько PDF-файлов)
 *
 * После успешного создания заказа клиент делает отдельный запрос
 * на этот endpoint, передавая файлы как multipart/form-data.
 */
#[Route('/orders/{id}/attachments', name: 'api_order_attachment_')]
#[IsGranted('ROLE_USER')]
class OrderAttachmentController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository          $orderRepository,
        private readonly OrderAttachmentUploader  $uploader,
        private readonly EntityManagerInterface   $em,
    ) {
    }

    /**
     * Загружает один или несколько PDF-файлов к существующему заказу.
     *
     * Возвращает список загруженных файлов:
     * { "uploaded": [{ "salt": "...", "name": "...", "size": 12345 }, ...] }
     */
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $order = $this->orderRepository->find($id);

        if (!$order || $order->getSender() !== $user) {
            return $this->json(['error' => 'Order not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        /** @var UploadedFile[] $files */
        $files = $request->files->get('files', []);

        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        if (empty($files)) {
            return $this->json(
                ['error' => 'No files provided. Send files as multipart/form-data under the "files[]" key.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $uploaded = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            if (!$file->isValid()) {
                return $this->json(
                    ['error' => sprintf('Upload error for file "%s": %s', $file->getClientOriginalName(), $file->getErrorMessage())],
                    JsonResponse::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            try {
                $attachment = $this->uploader->upload($file, $order);
                $uploaded[] = [
                    'salt' => $attachment->getSalt(),
                    'name' => $attachment->getOriginalName(),
                    'size' => $attachment->getFileSize(),
                ];
            } catch (\InvalidArgumentException $e) {
                return $this->json(['error' => $e->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            } catch (\RuntimeException $e) {
                return $this->json(['error' => 'File storage error. Please try again.'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        if (empty($uploaded)) {
            return $this->json(['error' => 'No valid files were uploaded.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json(['uploaded' => $uploaded], JsonResponse::HTTP_CREATED);
    }

    /**
     * Удаляет вложение по salt.
     * Доступно только владельцу заказа.
     */
    #[Route('/{salt}', name: 'delete', methods: ['DELETE'], requirements: ['salt' => '[0-9a-f]{64}'])]
    public function delete(string $id, string $salt): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $order = $this->orderRepository->find($id);

        if (!$order || $order->getSender() !== $user) {
            return $this->json(['error' => 'Order not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $attachment = $order->getAttachments()
            ->filter(fn($a) => $a->getSalt() === $salt)
            ->first();

        if (!$attachment) {
            return $this->json(['error' => 'Attachment not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->uploader->delete($attachment);
        $this->em->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
