<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Service;

use DateTimeImmutable;

/**
 * Training-load model derived from pace (no heart rate needed).
 *
 * - Per-run stress (rTSS): intensity factor = threshold pace / run pace, so a
 *   run at threshold ≈ IF 1.0; stress = hours × IF² × 100.
 * - Performance Management Chart: CTL (fitness, 42-day EWMA), ATL (fatigue,
 *   7-day EWMA), TSB (form) = yesterday's CTL − ATL.
 * - ACWR: acute (7-day load) ÷ chronic (28-day weekly-average load).
 * - Intensity distribution: time-in-zone by pace, to check the 80/20 balance.
 */
final class TrainingLoadCalculator
{
    /** Cap the intensity factor so a short very-fast rep can't distort the score. */
    private const IF_CAP = 1.15;

    public function stress(int $movingSeconds, float $paceSecondsPerKm, int $thresholdSecondsPerKm): float
    {
        if ($movingSeconds <= 0 || $paceSecondsPerKm <= 0.0 || $thresholdSecondsPerKm <= 0) {
            return 0.0;
        }

        $intensity = min($thresholdSecondsPerKm / $paceSecondsPerKm, self::IF_CAP);

        return ($movingSeconds / 3600) * $intensity * $intensity * 100;
    }

    /**
     * Day-by-day fitness / fatigue / form over an inclusive date range.
     *
     * @param array<string, float> $dailyStress date (Y-m-d) => summed stress
     *
     * @return list<array{date: string, ctl: float, atl: float, tsb: float}>
     */
    public function formSeries(array $dailyStress, string $from, string $to): array
    {
        $day = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);

        $ctl = 0.0;
        $atl = 0.0;
        $out = [];

        while ($day <= $end) {
            $key = $day->format('Y-m-d');
            $stress = $dailyStress[$key] ?? 0.0;

            // Form is measured before today's session lands.
            $tsb = $ctl - $atl;
            $ctl += ($stress - $ctl) / 42;
            $atl += ($stress - $atl) / 7;

            $out[] = ['date' => $key, 'ctl' => round($ctl, 1), 'atl' => round($atl, 1), 'tsb' => round($tsb, 1)];
            $day = $day->modify('+1 day');
        }

        return $out;
    }

    /**
     * Acute:chronic workload ratio at a given day. ~0.8–1.3 is the sweet spot,
     * > 1.5 flags a spike (injury risk).
     *
     * @param array<string, float> $dailyStress
     */
    public function acwr(array $dailyStress, string $asOf): float
    {
        $end = new DateTimeImmutable($asOf);
        $acute = 0.0;
        $chronic = 0.0;

        for ($i = 0; $i < 28; $i++) {
            $stress = $dailyStress[$end->modify("-{$i} days")->format('Y-m-d')] ?? 0.0;
            $chronic += $stress;
            if ($i < 7) {
                $acute += $stress;
            }
        }

        $chronicWeekly = $chronic / 4;

        return $chronicWeekly <= 0.0 ? 0.0 : round($acute / $chronicWeekly, 2);
    }

    /**
     * Seconds spent in each intensity zone, classified by pace vs the athlete's
     * marathon/threshold paces. Faster than threshold = hard; between threshold
     * and marathon = moderate ("grey zone"); marathon pace or slower = easy.
     *
     * @param list<array{seconds: int, paceSecondsPerKm: float}> $segments
     *
     * @return array{easy: int, moderate: int, hard: int}
     */
    public function intensityDistribution(array $segments, int $marathonSecondsPerKm, int $thresholdSecondsPerKm): array
    {
        $zones = ['easy' => 0, 'moderate' => 0, 'hard' => 0];

        foreach ($segments as $segment) {
            $pace = $segment['paceSecondsPerKm'];
            $seconds = $segment['seconds'];
            if ($pace <= 0.0 || $seconds <= 0) {
                continue;
            }

            if ($pace < $thresholdSecondsPerKm) {
                $zones['hard'] += $seconds;
            } elseif ($pace < $marathonSecondsPerKm) {
                $zones['moderate'] += $seconds;
            } else {
                $zones['easy'] += $seconds;
            }
        }

        return $zones;
    }
}
