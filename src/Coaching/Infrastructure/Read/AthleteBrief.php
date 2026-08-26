<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Read;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Domain\Service\AdaptationAnalyzer;
use Cadence\Coaching\Domain\Service\TrainingLoadCalculator;
use Cadence\Coaching\Domain\ValueObject\FitnessSnapshot;

/**
 * A compact, always-injected snapshot of the athlete's current state — real
 * recent sessions, form (80/20 + load) and the adaptation verdict — so every AI
 * plan is grounded in what the athlete actually did, not just the roadmap.
 */
final class AthleteBrief
{
    public static function build(string $tenantId, ?FitnessSnapshot $fitness, string $today): string
    {
        $lines = [];

        // Real recent sessions (ALL of them, not just the ones pinned to a slot).
        $runs = ActivityModel::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get(['occurred_at', 'distance_meters', 'average_pace_seconds_per_km', 'elevation_gain_meters']);

        $recent = [];
        foreach ($runs as $r) {
            $recent[] = sprintf(
                '%s %.1fkm @%s%s',
                date('d/m', (int) strtotime((string) $r->occurred_at)),
                (int) $r->distance_meters / 1000,
                self::pace((int) round((float) $r->average_pace_seconds_per_km)),
                (int) $r->elevation_gain_meters >= 100 ? ' (D+'.(int) $r->elevation_gain_meters.'m)' : '',
            );
        }
        $lines[] = $recent === []
            ? 'DERNIÈRES SORTIES : aucune enregistrée.'
            : 'DERNIÈRES SORTIES (base-toi dessus) : '.implode(' ; ', $recent).'.';

        if ($fitness !== null) {
            $load = TrainingLoadView::build($tenantId, $fitness, $today, new TrainingLoadCalculator());
            if (($load['hasData'] ?? false) === true) {
                $zones = is_array($load['zones'] ?? null) ? $load['zones'] : [];
                $total = (int) ($zones['total'] ?? 0);
                $easyPct = $total > 0 ? (int) round(100 * (int) ($zones['easy'] ?? 0) / $total) : 0;
                $chargeState = ($load['reliable'] ?? true) === true
                    ? sprintf('fitness %d, forme %+d, ratio de charge %s', (int) ($load['fitness'] ?? 0), (int) ($load['form'] ?? 0), number_format((float) ($load['acwr'] ?? 0), 2, ',', ''))
                    : 'en calibration (peu d’historique — à ne pas surinterpréter)';
                $lines[] = sprintf('FORME : ~%d%% du temps en facile sur 4 semaines (cible 80%%) ; charge : %s.', $easyPct, $chargeState);

                $adaptation = AdaptationView::build($tenantId, $load, $today, new AdaptationAnalyzer());
                if ($adaptation !== null) {
                    $lines[] = 'RECOMMANDATION MOTEUR : '.$adaptation['headline'].' ('.implode(', ', $adaptation['suggestions']).').';
                }
            }
        }

        return implode("\n        ", $lines);
    }

    private static function pace(int $secondsPerKm): string
    {
        if ($secondsPerKm <= 0) {
            return '—';
        }

        return intdiv($secondsPerKm, 60).':'.str_pad((string) ($secondsPerKm % 60), 2, '0', STR_PAD_LEFT);
    }
}
