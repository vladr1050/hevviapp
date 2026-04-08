<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Service\Invoice\DTO\InvoiceMapPayload;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Static map for invoice PDF: one OSM raster tile (PNG) + pickup/delivery pin positions.
 *
 * @see https://operations.osmfoundation.org/policies/tiles/
 */
final class InvoiceStaticMapFetcher
{
    private const OSM_TILE_BASE = 'https://tile.openstreetmap.org';

    /**
     * Outer map frame in CSS px (Twig uses pt; 1pt = 96/72 px in browsers).
     * Figma 773-5893: 240×118 pt, border 2.85 pt.
     */
    private const MAP_BOX_W_PX = 240.0 * 96.0 / 72.0;

    private const MAP_BOX_H_PX = 118.0 * 96.0 / 72.0;

    private const MAP_BORDER_PX = 2.85 * 96.0 / 72.0;

    private const TILE_PX = 256.0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Map image (data URI) and pin coordinates in the inner area below the black border.
     */
    public function fetchMapPayload(?string $pickLat, ?string $pickLon, ?string $dropLat, ?string $dropLon): InvoiceMapPayload
    {
        $a = $this->toFloat($pickLat, $pickLon);
        $b = $this->toFloat($dropLat, $dropLon);
        if ($a === null || $b === null) {
            return new InvoiceMapPayload($this->emptyPngDataUri(), false);
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

                return new InvoiceMapPayload($this->emptyPngDataUri(), false);
            }
            $bytes = $response->getContent();
            if (!$this->isPng($bytes)) {
                $this->logger->warning('Invoice map tile is not a PNG', [
                    'url' => $url,
                    'bytes' => strlen($bytes),
                ]);

                return new InvoiceMapPayload($this->emptyPngDataUri(), false);
            }

            $dataUri = 'data:image/png;base64,' . base64_encode($bytes);
            [$pL, $pT, $dL, $dT] = $this->pinPositionsInInnerBox($lat1, $lon1, $lat2, $lon2, $z, $tileX, $tileY);

            return new InvoiceMapPayload(
                $dataUri,
                true,
                sprintf('%.2f', $pL),
                sprintf('%.2f', $pT),
                sprintf('%.2f', $dL),
                sprintf('%.2f', $dT),
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Invoice map tile fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return new InvoiceMapPayload($this->emptyPngDataUri(), false);
        }
    }

    /**
     * CSS pixel position inside the map inner rect (object-fit: cover for 256 tile).
     *
     * @return array{0: float, 1: float, 2: float, 3: float} pickup left, top, drop left, top
     */
    private function pinPositionsInInnerBox(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        int $z,
        int $tileX,
        int $tileY,
    ): array {
        $innerW = self::MAP_BOX_W_PX - 2 * self::MAP_BORDER_PX;
        $innerH = self::MAP_BOX_H_PX - 2 * self::MAP_BORDER_PX;

        [$xf1, $yf1] = $this->projectToTileFractional($lat1, $lon1, $z);
        [$xf2, $yf2] = $this->projectToTileFractional($lat2, $lon2, $z);

        $px1 = max(0.0, min(self::TILE_PX, ($xf1 - $tileX) * self::TILE_PX));
        $py1 = max(0.0, min(self::TILE_PX, ($yf1 - $tileY) * self::TILE_PX));
        $px2 = max(0.0, min(self::TILE_PX, ($xf2 - $tileX) * self::TILE_PX));
        $py2 = max(0.0, min(self::TILE_PX, ($yf2 - $tileY) * self::TILE_PX));

        // Match CSS object-fit: cover — tile fills inner box, overflow cropped (center).
        $scale = max($innerW / self::TILE_PX, $innerH / self::TILE_PX);
        $offX = ($innerW - self::TILE_PX * $scale) / 2;
        $offY = ($innerH - self::TILE_PX * $scale) / 2;

        return [
            $offX + $px1 * $scale,
            $offY + $py1 * $scale,
            $offX + $px2 * $scale,
            $offY + $py2 * $scale,
        ];
    }

    /**
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
     * @return array{0: float, 1: float}
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
