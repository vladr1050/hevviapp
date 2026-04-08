<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Static map for invoice PDF: one OSM raster tile (PNG), embedded as data URI.
 *
 * Previously used staticmap.openstreetmap.de — often down, blocked, or returns HTML;
 * Chromium then showed a broken image. Official tile CDN + PNG validation is reliable.
 *
 * @see https://operations.osmfoundation.org/policies/tiles/
 */
final class InvoiceStaticMapFetcher
{
    private const OSM_TILE_BASE = 'https://tile.openstreetmap.org';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * PNG data URI or tiny transparent placeholder if coordinates missing / request fails.
     */
    public function fetchMapDataUri(?string $pickLat, ?string $pickLon, ?string $dropLat, ?string $dropLon): string
    {
        $a = $this->toFloat($pickLat, $pickLon);
        $b = $this->toFloat($dropLat, $dropLon);
        if ($a === null || $b === null) {
            return $this->emptyPngDataUri();
        }

        [$lat1, $lon1] = $a;
        [$lat2, $lon2] = $b;

        [$z, $tileX, $tileY] = $this->resolveTileCoveringBoth($lat1, $lon1, $lat2, $lon2);

        $url = sprintf('%s/%d/%d/%d.png', self::OSM_TILE_BASE, $z, $tileX, $tileY);

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'HevviInvoice/1.0 (+https://hevvi.app; invoice PDF map tile)',
                    'Accept' => 'image/png',
                ],
            ]);
            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Invoice map tile HTTP error', [
                    'url' => $url,
                    'status' => $response->getStatusCode(),
                ]);

                return $this->emptyPngDataUri();
            }
            $bytes = $response->getContent();
            if (!$this->isPng($bytes)) {
                $this->logger->warning('Invoice map tile is not a PNG', [
                    'url' => $url,
                    'bytes' => strlen($bytes),
                ]);

                return $this->emptyPngDataUri();
            }

            return 'data:image/png;base64,' . base64_encode($bytes);
        } catch (\Throwable $e) {
            $this->logger->warning('Invoice map tile fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyPngDataUri();
        }
    }

    /**
     * Pick zoom and tile indices so both points fall in the same standard map tile (if possible).
     *
     * @return array{0: int, 1: int, 2: int} z, tileX, tileY
     */
    private function resolveTileCoveringBoth(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        $centerLat = ($lat1 + $lat2) / 2;
        $centerLon = ($lon1 + $lon2) / 2;

        $minZoom = 5;
        $maxZoom = 16;

        for ($z = $maxZoom; $z >= $minZoom; --$z) {
            [$xf1, $yf1] = $this->projectToTileFractional($lat1, $lon1, $z);
            [$xf2, $yf2] = $this->projectToTileFractional($lat2, $lon2, $z);
            $tx1 = (int) floor($xf1);
            $ty1 = (int) floor($yf1);
            $tx2 = (int) floor($xf2);
            $ty2 = (int) floor($yf2);
            if ($tx1 === $tx2 && $ty1 === $ty2) {
                return [$z, $tx1, $ty1];
            }
        }

        [$xf, $yf] = $this->projectToTileFractional($centerLat, $centerLon, $minZoom);

        return [$minZoom, (int) floor($xf), (int) floor($yf)];
    }

    /**
     * @return array{0: float, 1: float} fractional tile X, Y (Web Mercator / OSM).
     */
    private function projectToTileFractional(float $lat, float $lon, int $zoom): array
    {
        $latRad = deg2rad($lat);
        $n = 2 ** $zoom;
        $xtf = ($lon + 180) / 360 * $n;
        $ytf = (1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * $n;

        return [$xtf, $ytf];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function toFloat(?string $lat, ?string $lon): ?array
    {
        if ($lat === null || $lon === null || $lat === '' || $lon === '') {
            return null;
        }
        $la = (float) $lat;
        $lo = (float) $lon;
        if (!is_finite($la) || !is_finite($lo)) {
            return null;
        }

        return [$la, $lo];
    }

    private function isPng(string $bytes): bool
    {
        return strlen($bytes) >= 8 && str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
    }

    private function emptyPngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }
}
