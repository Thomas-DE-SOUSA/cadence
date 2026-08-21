<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Gpx;

/** Extracts a downsampled [lat, lon] track and a distance/elevation/pace profile from a GPX document. */
final class GpxParser
{
    /**
     * @return list<array{lat:float,lon:float,ele:float,time:int}>
     */
    private static function points(string $xml): array
    {
        $doc = @simplexml_load_string($xml);
        if ($doc === false) {
            return [];
        }

        $doc->registerXPathNamespace('g', 'http://www.topografix.com/GPX/1/1');
        $nodes = $doc->xpath('//g:trkpt');
        if ($nodes === false || $nodes === null || $nodes === []) {
            $nodes = $doc->xpath('//trkpt') ?: [];
        }

        $points = [];
        foreach ($nodes as $p) {
            $lat = (float) ($p['lat'] ?? 0);
            $lon = (float) ($p['lon'] ?? 0);
            if ($lat === 0.0 || $lon === 0.0) {
                continue;
            }
            $ele = isset($p->ele) ? (float) $p->ele : 0.0;
            $time = isset($p->time) ? (int) strtotime((string) $p->time) : 0;
            $points[] = ['lat' => $lat, 'lon' => $lon, 'ele' => $ele, 'time' => $time];
        }

        return $points;
    }

    /**
     * @return list<array{0:float,1:float}>
     */
    public static function track(string $xml, int $maxPoints = 200): array
    {
        $all = self::points($xml);
        $count = count($all);
        if ($count === 0) {
            return [];
        }

        $stride = $count <= $maxPoints ? 1 : (int) ceil($count / $maxPoints);
        $sampled = [];
        for ($i = 0; $i < $count; $i += $stride) {
            $sampled[] = [round($all[$i]['lat'], 5), round($all[$i]['lon'], 5)];
        }
        $last = [round($all[$count - 1]['lat'], 5), round($all[$count - 1]['lon'], 5)];
        if ($sampled[count($sampled) - 1] !== $last) {
            $sampled[] = $last;
        }

        return $sampled;
    }

    /**
     * A downsampled profile: cumulative distance (m), elevation (m) and pace (s/km).
     *
     * @return list<array{d:int,e:int,p:int}>
     */
    public static function stream(string $xml, int $maxPoints = 160): array
    {
        $all = self::points($xml);
        $count = count($all);
        if ($count < 2) {
            return [];
        }

        // Cumulative distance per point.
        $cumulative = [0.0];
        for ($i = 1; $i < $count; $i++) {
            $cumulative[$i] = $cumulative[$i - 1] + self::haversine($all[$i - 1], $all[$i]);
        }

        $stride = $count <= $maxPoints ? 1 : (int) ceil($count / $maxPoints);
        $stream = [];
        $prev = 0;
        for ($i = $stride; $i < $count; $i += $stride) {
            $distDelta = $cumulative[$i] - $cumulative[$prev];
            $timeDelta = $all[$i]['time'] - $all[$prev]['time'];
            $pace = $distDelta > 5 && $timeDelta > 0 ? (int) round($timeDelta / ($distDelta / 1000)) : 0;
            if ($pace > 0 && $pace < 1500) {
                $stream[] = ['d' => (int) round($cumulative[$i]), 'e' => (int) round($all[$i]['ele']), 'p' => $pace];
            }
            $prev = $i;
        }

        return $stream;
    }

    /**
     * Full activity summary derived from a GPX: distance, times, elevation gain
     * and per-km splits (plus track + stream). Everything an import needs.
     *
     * @return array{externalId:string,occurredAt:string,distanceMeters:int,movingSeconds:int,elapsedSeconds:int,elevationGainMeters:int,splits:list<array{index:int,distanceMeters:int,durationSeconds:int,elevationMeters:int}>,track:list<array{0:float,1:float}>,stream:list<array{d:int,e:int,p:int}>}|null
     */
    public static function summary(string $xml): ?array
    {
        $points = self::points($xml);
        $count = count($points);
        if ($count < 2) {
            return null;
        }

        $cumulative = [0.0];
        $movingSeconds = 0;
        for ($i = 1; $i < $count; $i++) {
            $step = self::haversine($points[$i - 1], $points[$i]);
            $cumulative[$i] = $cumulative[$i - 1] + $step;
            $dt = $points[$i]['time'] - $points[$i - 1]['time'];
            if ($step > 0.5 && $dt > 0 && $dt < 30) {
                $movingSeconds += $dt;
            }
        }

        // Per-km splits.
        $splits = [];
        $target = 1000.0;
        $segStartTime = $points[0]['time'];
        $segStartEle = $points[0]['ele'];
        $segStartDist = 0.0;
        $index = 1;
        for ($i = 1; $i < $count; $i++) {
            if ($cumulative[$i] >= $target) {
                $splits[] = [
                    'index' => $index,
                    'distanceMeters' => (int) round($cumulative[$i] - $segStartDist),
                    'durationSeconds' => max(1, $points[$i]['time'] - $segStartTime),
                    'elevationMeters' => (int) round($points[$i]['ele'] - $segStartEle),
                ];
                $segStartTime = $points[$i]['time'];
                $segStartEle = $points[$i]['ele'];
                $segStartDist = $cumulative[$i];
                $target += 1000.0;
                $index++;
            }
        }
        // Final partial kilometre.
        if ($cumulative[$count - 1] - $segStartDist > 50) {
            $splits[] = [
                'index' => $index,
                'distanceMeters' => (int) round($cumulative[$count - 1] - $segStartDist),
                'durationSeconds' => max(1, $points[$count - 1]['time'] - $segStartTime),
                'elevationMeters' => (int) round($points[$count - 1]['ele'] - $segStartEle),
            ];
        }

        // Elevation gain with hysteresis to filter GPS jitter.
        $gain = 0.0;
        $confirmed = $points[0]['ele'];
        $peak = $confirmed;
        foreach ($points as $p) {
            $e = $p['ele'];
            if ($e > $peak) {
                $peak = $e;
            }
            if ($peak - $confirmed >= 3) {
                $gain += $peak - $confirmed;
                $confirmed = $peak;
            }
            if ($e < $confirmed) {
                $confirmed = $e;
                $peak = $e;
            }
        }

        $distanceMeters = array_sum(array_column($splits, 'distanceMeters'));
        $elapsed = $points[$count - 1]['time'] - $points[0]['time'];

        return [
            'externalId' => 'gpx-'.substr(sha1($xml), 0, 24),
            'occurredAt' => gmdate('Y-m-d\TH:i:s\Z', $points[0]['time']),
            'distanceMeters' => $distanceMeters,
            'movingSeconds' => $movingSeconds > 0 ? $movingSeconds : $elapsed,
            'elapsedSeconds' => max($elapsed, $movingSeconds),
            'elevationGainMeters' => (int) round($gain),
            'splits' => $splits,
            'track' => self::track($xml),
            'stream' => self::stream($xml),
        ];
    }

    /**
     * @param array{lat:float,lon:float,ele:float,time:int} $a
     * @param array{lat:float,lon:float,ele:float,time:int} $b
     */
    private static function haversine(array $a, array $b): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($b['lat'] - $a['lat']);
        $dLon = deg2rad($b['lon'] - $a['lon']);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a['lat'])) * cos(deg2rad($b['lat'])) * sin($dLon / 2) ** 2;

        return $r * 2 * asin(min(1.0, sqrt($h)));
    }
}
