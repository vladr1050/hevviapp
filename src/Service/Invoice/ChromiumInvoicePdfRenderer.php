<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Renders HTML from a temp dir (fonts + invoice-assets for file://). Uses DevTools
 * {@code Page.printToPDF} so A4 layout is not shrunk like with {@code --print-to-pdf} on some Chromium builds.
 */
final class ChromiumInvoicePdfRenderer
{
    /** 96dpi A4 in CSS px (210mm×297mm); must match print layout for html/body 210mm. */
    private const VIEWPORT_W = 794;

    private const VIEWPORT_H = 1123;

    public function __construct(
        private readonly string $chromeBinary,
        private readonly string $invoiceFontsSourceDir,
        private readonly string $invoiceAssetsDir,
    ) {
    }

    public function renderHtmlToPdf(string $html): string
    {
        $tmpDir = sys_get_temp_dir();
        $id = bin2hex(random_bytes(8));
        $workDir = $tmpDir . '/hevvi_inv_' . $id;
        $fontsDestDir = $workDir . '/fonts';
        $htmlPath = $workDir . '/index.html';
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

            $assetsDestDir = $workDir . '/invoice-assets';
            if (is_dir($this->invoiceAssetsDir)) {
                if (!@mkdir($assetsDestDir, 0700, true)) {
                    throw new \RuntimeException('Cannot create invoice-assets directory for PDF rendering.');
                }
                foreach (glob($this->invoiceAssetsDir . '/*.svg') ?: [] as $assetFile) {
                    $fs->copy($assetFile, $assetsDestDir . '/' . basename($assetFile), true);
                }
            }

            if (file_put_contents($htmlPath, $html) === false) {
                throw new \RuntimeException('Cannot write temporary HTML for PDF rendering.');
            }

            $fileUrl = 'file://' . str_replace('\\', '/', $htmlPath);
            $factory = new BrowserFactory($this->chromeBinary);
            $browser = $factory->createBrowser([
                'headless' => true,
                'noSandbox' => true,
                'windowSize' => [self::VIEWPORT_W, self::VIEWPORT_H],
                'customFlags' => [
                    '--disable-gpu',
                    '--disable-dev-shm-usage',
                    '--force-device-scale-factor=1',
                ],
                'startupTimeout' => 60,
                'sendSyncDefaultTimeout' => 120000,
            ]);

            try {
                $page = $browser->createPage();
                $page->setViewport(self::VIEWPORT_W, self::VIEWPORT_H)->await(5000);
                $page->navigate($fileUrl)->waitForNavigation(Page::LOAD, 60000);

                return $page->pdf([
                    'printBackground' => true,
                    'preferCSSPageSize' => true,
                    'marginTop' => 0.0,
                    'marginBottom' => 0.0,
                    'marginLeft' => 0.0,
                    'marginRight' => 0.0,
                    'scale' => 1.0,
                ])->getRawBinary(120000);
            } finally {
                $browser->close();
            }
        } finally {
            $fs->remove($workDir);
        }
    }
}
