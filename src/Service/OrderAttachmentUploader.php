<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 */

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderAttachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Отвечает за:
 * - валидацию PDF-файлов по MIME-типу
 * - генерацию криптографически-стойкого salt (64 hex-символа)
 * - gzip-сжатие (уровень 6) для экономии места на диске
 * - сохранение файла в public/uploads/orders/
 * - создание и persist сущности OrderAttachment
 * - удаление файла с диска при remove
 */
class OrderAttachmentUploader
{
    private const UPLOAD_SUBDIR   = 'uploads/orders';
    private const COMPRESS_LEVEL  = 6;
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/x-pdf',
        'image/png',
        'image/jpeg',
        'image/jpg',
    ];

    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg'];

    public function __construct(
        private readonly string $publicDir,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Загружает, сжимает и сохраняет файл к заказу.
     * persist вызывается внутри; flush — на стороне вызывающего кода.
     *
     * @throws \InvalidArgumentException если файл не PDF/PNG/JPG
     * @throws \RuntimeException         если не удалось записать файл
     */
    public function upload(UploadedFile $file, Order $order): OrderAttachment
    {
        $this->assertAllowedFile($file);

        $salt      = bin2hex(random_bytes(32));
        $targetDir = $this->publicDir . '/' . self::UPLOAD_SUBDIR;
        $this->ensureDirectoryExists($targetDir);

        $extension   = $this->resolveStorageExtension($file);
        $relPath     = self::UPLOAD_SUBDIR . '/' . $salt . '.' . $extension . '.gz';
        $absolutePath = $this->publicDir . '/' . $relPath;

        $this->compressToGzip($file->getPathname(), $absolutePath);

        $attachment = new OrderAttachment();
        $attachment->setSalt($salt);
        $attachment->setFilePath($relPath);
        $attachment->setOriginalName($file->getClientOriginalName() ?: 'document.' . $extension);
        $attachment->setFileSize((int) filesize($absolutePath));
        $attachment->setRelatedOrder($order);

        $this->em->persist($attachment);

        return $attachment;
    }

    /**
     * Удаляет файл с диска и помечает сущность для удаления из БД.
     * flush — на стороне вызывающего кода.
     */
    public function delete(OrderAttachment $attachment): void
    {
        $absolutePath = $this->publicDir . '/' . $attachment->getFilePath();

        if (is_file($absolutePath)) {
            unlink($absolutePath);
        }

        $this->em->remove($attachment);
    }

    // ------------------------------------------------------------------ private

    private function assertAllowedFile(UploadedFile $file): void
    {
        $mime = $file->getMimeType() ?? '';
        $extension = strtolower(pathinfo($file->getClientOriginalName() ?: '', PATHINFO_EXTENSION));

        if (in_array($mime, self::ALLOWED_MIMES, true)) {
            return;
        }

        if (in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return;
        }

        throw new \InvalidArgumentException(
            sprintf(
                'Only PDF, PNG, and JPG files are allowed. Got: "%s".',
                $mime !== '' ? $mime : $extension
            )
        );
    }

    private function resolveStorageExtension(UploadedFile $file): string
    {
        $mime = $file->getMimeType() ?? '';
        $extension = strtolower(pathinfo($file->getClientOriginalName() ?: '', PATHINFO_EXTENSION));

        if ('image/png' === $mime || 'png' === $extension) {
            return 'png';
        }

        if (
            in_array($mime, ['image/jpeg', 'image/jpg'], true)
            || in_array($extension, ['jpg', 'jpeg'], true)
        ) {
            return 'jpg';
        }

        return 'pdf';
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create upload directory "%s".', $dir));
        }
    }

    private function compressToGzip(string $sourcePath, string $targetPath): void
    {
        $content = file_get_contents($sourcePath);

        if ($content === false) {
            throw new \RuntimeException(sprintf('Cannot read source file "%s".', $sourcePath));
        }

        $compressed = gzencode($content, self::COMPRESS_LEVEL);

        if ($compressed === false) {
            throw new \RuntimeException('gzip compression failed.');
        }

        if (file_put_contents($targetPath, $compressed) === false) {
            throw new \RuntimeException(sprintf('Cannot write compressed file to "%s".', $targetPath));
        }
    }
}
