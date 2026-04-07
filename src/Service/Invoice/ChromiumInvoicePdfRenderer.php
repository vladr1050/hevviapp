<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use Symfony\Component\Process\Process;

final class ChromiumInvoicePdfRenderer
{
    public function __construct(
        private readonly string $chromeBinary,
    ) {
    }

    public function renderHtmlToPdf(string $html): string
    {
        $tmpDir = sys_get_temp_dir();
        $id = bin2hex(random_bytes(8));
        $htmlPath = $tmpDir . '/hevvi_inv_' . $id . '.html';
        $pdfPath = $tmpDir . '/hevvi_inv_' . $id . '.pdf';

        file_put_contents($htmlPath, $html);

        try {
            $process = new Process([
                $this->chromeBinary,
                '--headless=new',
                '--disable-gpu',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--print-to-pdf=' . $pdfPath,
                'file://' . $htmlPath,
            ]);
            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput() . ' ' . $process->getOutput()));
            }

            if (!is_file($pdfPath) || !is_readable($pdfPath)) {
                throw new \RuntimeException('Chromium did not produce a PDF file.');
            }

            $pdf = file_get_contents($pdfPath);
            if ($pdf === false || $pdf === '') {
                throw new \RuntimeException('Empty PDF output.');
            }

            return $pdf;
        } finally {
            @unlink($htmlPath);
            @unlink($pdfPath);
        }
    }
}
