<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Service\Invoice\DTO\InvoiceMapPayload;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Static map for invoice PDF: one or two stitched OSM raster tiles (PNG) + pickup/delivery pins.
 * Chooses the highest zoom where the padded bbox fits in at most two adjacent tiles (1×1, 2×1, or 1×2),
 * fetches those tiles, scales/pans so both pins stay inside the frame with padding and the route midpoint
 * is centered when the feasible range allows.
 *
 * @see https://operations.osmfoundation.org/policies/tiles/
 */
final class InvoiceStaticMapFetcher
{
    private const OSM_TILE_BASE = 'https://tile.openstreetmap.org';

    /** Invoice PDF spec: map column 240×118px (main content 500px, route 240 + gap 20 + map 240). */
    private const MAP_BOX_W_PX = 240.0;

    private const MAP_BOX_H_PX = 118.0;

    private const MAP_BORDER_PX = 0.0;

    /** Inner padding (~fitBounds) in CSS px for small preview. */
    private const INNER_ROUTE_PAD_PX = 14.0;

    private const BBOX_PAD_LATLON = 0.18;

    private const TILE_PX = 256.0;

    private const MIN_ZOOM = 4;

    private const MAX_ZOOM = 16;

    /** Max uniform scale-up over min cover scale (pan range + pin visibility). */
    private const MAP_CENTER_MAX_SCALE_FACTOR = 2.8;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    private function mapOuterWidthPx(): float
    {
        return self::MAP_BOX_W_PX;
    }

    public function fetchMapPayload(?string $pickLat, ?string $pickLon, ?string $dropLat, ?string $dropLon): InvoiceMapPayload
    {
        $a = $this->toFloat($pickLat, $pickLon);
        $b = $this->toFloat($dropLat, $dropLon);
        if ($a === null || $b === null) {
            return new InvoiceMapPayload($this->emptyPngDataUri(), false);
        }

        [$lat1, $lon1] = $a;
        [$lat2, $lon2] = $b;

        $innerW = $this->mapOuterWidthPx() - 2 * self::MAP_BORDER_PX;
        $innerH = self::MAP_BOX_H_PX - 2 * self::MAP_BORDER_PX;

        [$z, $txOrigin, $tyOrigin, $tilesX, $tilesY, $layout] = $this->resolveTileLayout($lat1, $lon1, $lat2, $lon2, $innerW, $innerH);

        $urls = [];
        for ($ty = 0; $ty < $tilesY; ++$ty) {
            for ($tx = 0; $tx < $tilesX; ++$tx) {
                $urls[] = sprintf('%s/%d/%d/%d.png', self::OSM_TILE_BASE, $z, $txOrigin + $tx, $tyOrigin + $ty);
            }
        }

        try {
            $bytesList = [];
            foreach ($urls as $url) {
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
                $bytesList[] = $bytes;
            }

            $firstUri = 'data:image/png;base64,' . base64_encode($bytesList[0]);
            $secondUri = isset($bytesList[1]) ? 'data:image/png;base64,' . base64_encode($bytesList[1]) : null;

            [$pL, $pT, $dL, $dT, $ox, $oy, $scale] = $layout;
            $virtualW = $tilesX * self::TILE_PX;
            $virtualH = $tilesY * self::TILE_PX;
            $imgW = $virtualW * $scale;
            $imgH = $virtualH * $scale;

            return new InvoiceMapPayload(
                $firstUri,
                true,
                sprintf('%.2f', $pL),
                sprintf('%.2f', $pT),
                sprintf('%.2f', $dL),
                sprintf('%.2f', $dT),
                sprintf('%.2f', $innerW),
                sprintf('%.2f', $innerH),
                sprintf('%.2f', $ox),
                sprintf('%.2f', $oy),
                sprintf('%.2f', $imgW),
                sprintf('%.2f', $imgH),
                $secondUri,
                $tilesX,
                $tilesY,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Invoice map tile fetch failed', [
                'urls' => $urls,
                'error' => $e->getMessage(),
            ]);

            return new InvoiceMapPayload($this->emptyPngDataUri(), false);
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}}
     */
    private function resolveTileLayout(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        float $innerW,
        float $innerH,
    ): array {
        [$minLat, $minLon, $maxLat, $maxLon] = $this->paddedLatLonBBox($lat1, $lon1, $lat2, $lon2);

        for ($z = self::MAX_ZOOM; $z >= self::MIN_ZOOM; --$z) {
            $strip = $this->tryStripLayout(
                $lat1,
                $lon1,
                $lat2,
                $lon2,
                $minLat,
                $minLon,
                $maxLat,
                $maxLon,
                $z,
                $innerW,
                $innerH,
                true,
            );
            if ($strip !== null) {
                return [$z, $strip[0], $strip[1], $strip[2], $strip[3], $strip[4]];
            }
        }

        return $this->fallbackLegacyTileLayout($lat1, $lon1, $lat2, $lon2, $innerW, $innerH);
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}}|null
     */
    private function tryStripLayout(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        float $minLat,
        float $minLon,
        float $maxLat,
        float $maxLon,
        int $z,
        float $innerW,
        float $innerH,
        bool $requirePaddingSpan,
    ): ?array {
        [$minTx, $maxTx, $minTy, $maxTy] = $this->tileIndexBoundsForLatLonBBox($minLat, $minLon, $maxLat, $maxLon, $z);
        $wTiles = $maxTx - $minTx + 1;
        $hTiles = $maxTy - $minTy + 1;
        if ($wTiles > 2 || $hTiles > 2) {
            return null;
        }
        if ($wTiles === 2 && $hTiles === 2) {
            return null;
        }

        $txOrigin = $minTx;
        $tyOrigin = $minTy;
        $tilesX = $wTiles;
        $tilesY = $hTiles;

        foreach ([[$lat1, $lon1], [$lat2, $lon2]] as [$la, $lo]) {
            [$xf, $yf] = $this->projectToTileFractional($la, $lo, $z);
            $ftx = (int) floor($xf);
            $fty = (int) floor($yf);
            if ($ftx < $txOrigin || $ftx >= $txOrigin + $tilesX
                || $fty < $tyOrigin || $fty >= $tyOrigin + $tilesY) {
                return null;
            }
        }

        $layout = $this->layoutInInnerBox(
            $lat1,
            $lon1,
            $lat2,
            $lon2,
            $z,
            $txOrigin,
            $tyOrigin,
            $tilesX,
            $tilesY,
            $innerW,
            $innerH,
            self::INNER_ROUTE_PAD_PX,
            $requirePaddingSpan,
        );

        return $layout === null ? null : [$txOrigin, $tyOrigin, $tilesX, $tilesY, $layout];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function tileIndexBoundsForLatLonBBox(float $minLat, float $minLon, float $maxLat, float $maxLon, int $z): array
    {
        $corners = [
            [$minLat, $minLon],
            [$minLat, $maxLon],
            [$maxLat, $minLon],
            [$maxLat, $maxLon],
        ];
        $minTx = PHP_INT_MAX;
        $maxTx = PHP_INT_MIN;
        $minTy = PHP_INT_MAX;
        $maxTy = PHP_INT_MIN;
        foreach ($corners as [$la, $lo]) {
            [$xf, $yf] = $this->projectToTileFractional($la, $lo, $z);
            $tx = (int) floor($xf);
            $ty = (int) floor($yf);
            $minTx = min($minTx, $tx);
            $maxTx = max($maxTx, $tx);
            $minTy = min($minTy, $ty);
            $maxTy = max($maxTy, $ty);
        }

        return [$minTx, $maxTx, $minTy, $maxTy];
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function paddedLatLonBBox(float $lat1, float $lon1, float $lat2, float $lon2): array
    {
        $minLat = min($lat1, $lat2);
        $maxLat = max($lat1, $lat2);
        $minLon = min($lon1, $lon2);
        $maxLon = max($lon1, $lon2);
        $dLat = $maxLat - $minLat;
        $dLon = $maxLon - $minLon;
        $padLat = max(0.001, $dLat * self::BBOX_PAD_LATLON + 0.003);
        $padLon = max(0.001, $dLon * self::BBOX_PAD_LATLON + 0.003);

        return [$minLat - $padLat, $minLon - $padLon, $maxLat + $padLat, $maxLon + $padLon];
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}|null pickup L, T, drop L, T, img ox, oy, scale
     */
    private function layoutInInnerBox(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        int $z,
        int $txOrigin,
        int $tyOrigin,
        int $tilesX,
        int $tilesY,
        float $innerW,
        float $innerH,
        float $padPx,
        bool $requirePaddingSpan,
    ): ?array {
        [$xf1, $yf1] = $this->projectToTileFractional($lat1, $lon1, $z);
        [$xf2, $yf2] = $this->projectToTileFractional($lat2, $lon2, $z);

        $px1 = ($xf1 - $txOrigin) * self::TILE_PX;
        $py1 = ($yf1 - $tyOrigin) * self::TILE_PX;
        $px2 = ($xf2 - $txOrigin) * self::TILE_PX;
        $py2 = ($yf2 - $tyOrigin) * self::TILE_PX;

        $minPx = min($px1, $px2);
        $maxPx = max($px1, $px2);
        $minPy = min($py1, $py2);
        $maxPy = max($py1, $py2);

        $midPx = ($minPx + $maxPx) / 2;
        $midPy = ($minPy + $maxPy) / 2;

        $spanPxWorld = $maxPx - $minPx;
        $spanPyWorld = $maxPy - $minPy;

        $virtualW = $tilesX * self::TILE_PX;
        $virtualH = $tilesY * self::TILE_PX;

        $baseScale = max($innerW / $virtualW, $innerH / $virtualH);
        $spanX0 = $spanPxWorld * $baseScale;
        $spanY0 = $spanPyWorld * $baseScale;
        if ($requirePaddingSpan && ($spanX0 + 2 * $padPx > $innerW + 0.5 || $spanY0 + 2 * $padPx > $innerH + 0.5)) {
            return null;
        }

        $fHi = self::MAP_CENTER_MAX_SCALE_FACTOR;
        if ($requirePaddingSpan) {
            if ($spanPxWorld > 1e-6) {
                $fHi = min($fHi, ($innerW - 2 * $padPx + 0.5) / ($spanPxWorld * $baseScale));
            }
            if ($spanPyWorld > 1e-6) {
                $fHi = min($fHi, ($innerH - 2 * $padPx + 0.5) / ($spanPyWorld * $baseScale));
            }
        }
        if ($fHi < 1.0 - 1e-9) {
            return null;
        }

        $best = null;
        $bestErr = INF;
        for ($f = 1.0; $f <= $fHi + 1e-6; $f += 0.02) {
            $scale = $baseScale * $f;
            if ($requirePaddingSpan) {
                $spanX = $spanPxWorld * $scale;
                $spanY = $spanPyWorld * $scale;
                if ($spanX + 2 * $padPx > $innerW + 0.5 || $spanY + 2 * $padPx > $innerH + 0.5) {
                    break;
                }
            }

            $sw = $virtualW * $scale;
            $sh = $virtualH * $scale;

            $oxIdeal = $innerW / 2 - $midPx * $scale;
            $oyIdeal = $innerH / 2 - $midPy * $scale;

            $oxTileMin = $innerW - $sw;
            $oxTileMax = 0.0;
            $oxPinMin = $padPx - $minPx * $scale;
            $oxPinMax = $innerW - $padPx - $maxPx * $scale;
            $oxLo = max($oxTileMin, $oxPinMin);
            $oxHi = min($oxTileMax, $oxPinMax);

            $oyTileMin = $innerH - $sh;
            $oyTileMax = 0.0;
            $oyPinMin = $padPx - $minPy * $scale;
            $oyPinMax = $innerH - $padPx - $maxPy * $scale;
            $oyLo = max($oyTileMin, $oyPinMin);
            $oyHi = min($oyTileMax, $oyPinMax);

            if ($oxLo > $oxHi || $oyLo > $oyHi) {
                continue;
            }

            $ox = max($oxLo, min($oxHi, $oxIdeal));
            $oy = max($oyLo, min($oyHi, $oyIdeal));
            $err = abs($ox - $oxIdeal) + abs($oy - $oyIdeal);

            $pL = $ox + $px1 * $scale;
            $pT = $oy + $py1 * $scale;
            $dL = $ox + $px2 * $scale;
            $dT = $oy + $py2 * $scale;
            $candidate = [$pL, $pT, $dL, $dT, $ox, $oy, $scale];
            if ($err < $bestErr) {
                $bestErr = $err;
                $best = $candidate;
            }
            if ($err < 0.25) {
                return $candidate;
            }
        }

        return $best;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}}
     */
    private function fallbackLegacyTileLayout(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2,
        float $innerW,
        float $innerH,
    ): array {
        [$minLat, $minLon, $maxLat, $maxLon] = $this->paddedLatLonBBox($lat1, $lon1, $lat2, $lon2);

        for ($z = self::MAX_ZOOM; $z >= self::MIN_ZOOM; --$z) {
            $strip = $this->tryStripLayout(
                $lat1,
                $lon1,
                $lat2,
                $lon2,
                $minLat,
                $minLon,
                $maxLat,
                $maxLon,
                $z,
                $innerW,
                $innerH,
                false,
            );
            if ($strip !== null) {
                return [$z, $strip[0], $strip[1], $strip[2], $strip[3], $strip[4]];
            }
        }

        $z = self::MIN_ZOOM;
        $centerLat = ($lat1 + $lat2) / 2;
        $centerLon = ($lon1 + $lon2) / 2;
        [$xf, $yf] = $this->projectToTileFractional($centerLat, $centerLon, $z);
        $tileX = (int) floor($xf);
        $tileY = (int) floor($yf);
        $layout = $this->layoutInInnerBox(
            $lat1,
            $lon1,
            $lat2,
            $lon2,
            $z,
            $tileX,
            $tileY,
            1,
            1,
            $innerW,
            $innerH,
            self::INNER_ROUTE_PAD_PX,
            false,
        );
        if ($layout === null) {
            $layout = [0.0, 0.0, 0.0, 0.0, 0.0, 0.0, max($innerW / self::TILE_PX, $innerH / self::TILE_PX)];
        }

        return [$z, $tileX, $tileY, 1, 1, $layout];
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
