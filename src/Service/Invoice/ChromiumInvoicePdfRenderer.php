<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Renders HTML from a temporary directory so relative {@code url('fonts/…')} resolve under file://
 * (Chromium loads the page from disk; {@code /public/fonts} URLs would not work).
 */
final class ChromiumInvoicePdfRenderer
{
    public function __construct(
        private readonly string $chromeBinary,
        private readonly string $invoiceFontsSourceDir,
    ) {
    }

    public function renderHtmlToPdf(string $html): string
    {
        $tmpDir = sys_get_temp_dir();
        $id = bin2hex(random_bytes(8));
        $workDir = $tmpDir . '/hevvi_inv_' . $id;
        $fontsDestDir = $workDir . '/fonts';
        $htmlPath = $workDir . '/index.html';
        $pdfPath = $workDir . '/output.pdf';
        $fs = new Filesystem();

        if (!@mkdir($workDir, 0700, true) || !@mkdir($fontsDestDir, 0700, true)) {
            throw new \RuntimeException('Cannot create temporary directory for PDF rendering.');
        }

        try {
            if (is_dir($this->invoiceFontsSourceDir)) {
                foreach (glob($this->invoiceFontsSourceDir . '/*.woff2') ?: [] as $fontFile) {
                    $fs->copy($fontFile, $fontsDestDir . '/' . basename($fontFile), true);
                }
            }

            if (file_put_contents($htmlPath, $html) === false) {
                throw new \RuntimeException('Cannot write temporary HTML for PDF rendering.');
            }

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
            $fs->remove($workDir);
        }
    }
}
