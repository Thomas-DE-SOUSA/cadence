<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Service;

use Cadence\Coaching\Domain\ValueObject\FitnessSnapshot;
use Cadence\Coaching\Domain\ValueObject\PerformancePoint;
use Cadence\Coaching\Domain\ValueObject\TrainingPaces;
use DateTimeImmutable;

/**
 * Builds a FitnessSnapshot from logged runs: VDOT from the best qualifying
 * effort, personalised paces, and recent load over a 28-day window ending at
 * the most recent run (deterministic — no wall-clock dependency).
 */
final class FitnessAssessor
{
    /** Efforts shorter than this are ignored for VDOT (too noisy). */
    private const MIN_VDOT_DISTANCE_METERS = 2000;

    private const RECENT_WINDOW_DAYS = 28;

    public function __construct(private readonly VdotCalculator $vdot)
    {
    }

    /**
     * @param list<PerformancePoint> $history
     */
    public function assess(array $history): ?FitnessSnapshot
    {
        $qualifying = array_values(array_filter(
            $history,
            fn (PerformancePoint $p): bool => $p->distanceMeters >= self::MIN_VDOT_DISTANCE_METERS && $p->movingSeconds > 0,
        ));

        if ($qualifying === []) {
            return null;
        }

        $best = $qualifying[0];
        $bestVdot = $this->vdot->vdot($best->distanceMeters, $best->movingSeconds);
        foreach ($qualifying as $point) {
            $candidate = $this->vdot->vdot($point->distanceMeters, $point->movingSeconds);
            if ($candidate > $bestVdot) {
                $bestVdot = $candidate;
                $best = $point;
            }
        }

        $paces = new TrainingPaces(
            $this->vdot->paceSecondsPerKm($bestVdot, VdotCalculator::EASY),
            $this->vdot->paceSecondsPerKm($bestVdot, VdotCalculator::MARATHON),
            $this->vdot->paceSecondsPerKm($bestVdot, VdotCalculator::THRESHOLD),
            $this->vdot->paceSecondsPerKm($bestVdot, VdotCalculator::INTERVAL),
            $this->vdot->paceSecondsPerKm($bestVdot, VdotCalculator::REPETITION),
        );

        [$recentVolume, $longest, $recentCount] = $this->recentLoad($history);

        return new FitnessSnapshot(
            round($bestVdot, 1),
            $best->distanceMeters,
            $best->movingSeconds,
            substr($best->occurredAt, 0, 10),
            $paces,
            $recentVolume,
            $longest,
            $recentCount,
        );
    }

    /**
     * @param list<PerformancePoint> $history
     *
     * @return array{0:int,1:int,2:int} recent volume (m), longest run (m), run count
     */
    private function recentLoad(array $history): array
    {
        if ($history === []) {
            return [0, 0, 0];
        }

        $latest = $history[0]->occurredAt;
        foreach ($history as $point) {
            if ($point->occurredAt > $latest) {
                $latest = $point->occurredAt;
            }
        }

        $windowStart = (new DateTimeImmutable(substr($latest, 0, 10)))
            ->modify('-'.self::RECENT_WINDOW_DAYS.' days')
            ->format('Y-m-d');

        $volume = 0;
        $longest = 0;
        $count = 0;
        foreach ($history as $point) {
            if (substr($point->occurredAt, 0, 10) >= $windowStart) {
                $volume += $point->distanceMeters;
                $longest = max($longest, $point->distanceMeters);
                $count++;
            }
        }

        return [$volume, $longest, $count];
    }
}
