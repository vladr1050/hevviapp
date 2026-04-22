<?php

declare(strict_types=1);

namespace App\Service\Document;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Order-scoped PDF storage under var/storage/documents/{orderUuid}/… with legacy var/invoices read fallback.
 */
final class StoredPdfPathResolver
{
    public function __construct(
        #[Autowire('%app.document_storage_dir%')]
        private readonly string $primaryDir,
        #[Autowire('%app.legacy_invoice_storage_dir%')]
        private readonly string $legacyDir,
    ) {
    }

    public function getPrimaryDir(): string
    {
        return $this->primaryDir;
    }

    /**
     * Absolute path to an existing readable file, or null.
     */
    public function resolveReadableFile(?string $relative): ?string
    {
        $relative = $this->sanitizeRelative($relative);
        if ($relative === null) {
            return null;
        }

        foreach ([$this->primaryDir, $this->legacyDir] as $base) {
            $full = $base . '/' . $relative;
            if (is_readable($full)) {
                $real = realpath($full);

                return $real !== false ? $real : null;
            }
        }

        return null;
    }

    /**
     * True if the real path is inside primary or legacy storage (path traversal safe).
     */
    public function isAllowedAbsolutePath(string $absolutePath): bool
    {
        $fileReal = realpath($absolutePath);
        if ($fileReal === false || !is_file($fileReal)) {
            return false;
        }

        foreach ([$this->primaryDir, $this->legacyDir] as $base) {
            $baseReal = realpath($base);
            if ($baseReal !== false && str_starts_with($fileReal, $baseReal . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeRelative(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }
        $relative = str_replace(["\0", '\\'], '', $relative);
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return $relative;
    }
}
