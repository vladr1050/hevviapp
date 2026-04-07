<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Static map image (two markers, no route) for embedding in PDF.
 */
final class InvoiceStaticMapFetcher
{
    private const WIDTH = 480;

    private const HEIGHT = 236;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
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

        $centerLat = ($lat1 + $lat2) / 2;
        $centerLon = ($lon1 + $lon2) / 2;
        $zoom = $this->estimateZoom($lat1, $lon1, $lat2, $lon2);

        $url = sprintf(
            'https://staticmap.openstreetmap.de/staticmap.php?center=%F,%F&zoom=%d&size=%dx%d&maptype=mapnik&markers=%F,%F,lightblue1&markers=%F,%F,red1',
            $centerLat,
            $centerLon,
            $zoom,
            self::WIDTH,
            self::HEIGHT,
            $lat1,
            $lon1,
            $lat2,
            $lon2
        );

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'HevviInvoice/1.0 (billing)',
                ],
            ]);
            if ($response->getStatusCode() !== 200) {
                return $this->emptyPngDataUri();
            }
            $bytes = $response->getContent();

            return 'data:image/png;base64,' . base64_encode($bytes);
        } catch (\Throwable) {
            return $this->emptyPngDataUri();
        }
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

    private function estimateZoom(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $dLat = abs($lat1 - $lat2);
        $dLon = abs($lon1 - $lon2);
        $d = max($dLat, $dLon);
        if ($d < 0.02) {
            return 14;
        }
        if ($d < 0.08) {
            return 12;
        }
        if ($d < 0.2) {
            return 11;
        }

        return 10;
    }

    private function emptyPngDataUri(): string
    {
        // 1×1 transparent PNG
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }
}
