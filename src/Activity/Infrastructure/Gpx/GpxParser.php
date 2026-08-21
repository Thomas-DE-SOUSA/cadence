<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Gpx;

/** Extracts a downsampled [lat, lon] track from a GPX document. */
final class GpxParser
{
    /**
     * @return list<array{0:float,1:float}>
     */
    public static function track(string $xml, int $maxPoints = 180): array
    {
        $doc = @simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $doc->registerXPathNamespace('g', 'http://www.topografix.com/GPX/1/1');
        $points = $doc->xpath('//g:trkpt');
        if ($points === false || $points === null || $points === []) {
            $points = $doc->xpath('//trkpt') ?: [];
        }

        $all = [];
        foreach ($points as $p) {
            $lat = (float) ($p['lat'] ?? 0);
            $lon = (float) ($p['lon'] ?? 0);
            if ($lat !== 0.0 && $lon !== 0.0) {
                $all[] = [$lat, $lon];
            }
        }

        $count = count($all);
        if ($count === 0) {
            return [];
        }

        if ($count <= $maxPoints) {
            $sampled = $all;
        } else {
            $stride = (int) ceil($count / $maxPoints);
            $sampled = [];
            for ($i = 0; $i < $count; $i += $stride) {
                $sampled[] = $all[$i];
            }
            if ($sampled[count($sampled) - 1] !== $all[$count - 1]) {
                $sampled[] = $all[$count - 1];
            }
        }

        return array_map(static fn (array $p): array => [round($p[0], 5), round($p[1], 5)], $sampled);
    }
}
