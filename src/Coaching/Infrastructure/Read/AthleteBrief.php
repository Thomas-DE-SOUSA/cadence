<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Read;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Domain\Service\AdaptationAnalyzer;
use Cadence\Coaching\Domain\Service\TrainingLoadCalculator;
use Cadence\Coaching\Domain\ValueObject\FitnessSnapshot;
use DateTimeImmutable;

/**
 * An always-injected *analysis* of the athlete's recent training (not a raw
 * dump): volume trend, easy-pace discipline, longest run, plus form (80/20 +
 * load) and the adaptation verdict — so every AI plan is grounded and on-goal.
 */
final class AthleteBrief
{
    public static function build(string $tenantId, ?FitnessSnapshot $fitness, string $today): string
    {
        $todayD = new DateTimeImmutable($today);
        $cut = $todayD->modify('-27 days')->format('Y-m-d');

        $runs = ActivityModel::query()
            ->where('tenant_id', $tenantId)
            ->where('occurred_at', '>=', $cut.' 00:00:00')
            ->orderByDesc('occurred_at')
            ->get(['occurred_at', 'distance_meters', 'average_pace_seconds_per_km']);

        $threshold = $fitness?->paces->threshold ?? 0;
        $easyTarget = $fitness?->paces->easy ?? 0;

        $vol7 = 0.0;
        $volPrev7 = 0.0;
        $count7 = 0;
        $longest = 0.0;
        $aeroPaceKm = 0.0;
        $aeroKm = 0.0;

        foreach ($runs as $r) {
            $km = (int) $r->distance_meters / 1000;
            $pace = (float) $r->average_pace_seconds_per_km;
            $age = (int) $todayD->diff(new DateTimeImmutable(substr((string) $r->occurred_at, 0, 10)))->days;

            if ($age <= 6) {
                $vol7 += $km;
                $count7++;
            } elseif ($age <= 13) {
                $volPrev7 += $km;
            }
            $longest = max($longest, $km);
            if ($threshold > 0 && $pace >= $threshold) { // aerobic (not a quality rep)
                $aeroPaceKm += $pace * $km;
                $aeroKm += $km;
            }
        }

        if ($runs->isEmpty()) {
            return 'ANALYSE : aucune sortie récente enregistrée — démarre prudemment.';
        }

        $lines = ['ANALYSE de l’historique récent (4 semaines) :'];

        $trend = $volPrev7 <= 0.0 ? '' : ($vol7 > $volPrev7 * 1.1 ? ', en hausse' : ($vol7 < $volPrev7 * 0.9 ? ', en baisse' : ', stable'))
            .($volPrev7 > 0 ? ' vs '.round($volPrev7).' km la semaine d’avant' : '');
        $lines[] = sprintf('- Volume 7 j : %s km (%d sortie%s%s).', round($vol7), $count7, $count7 > 1 ? 's' : '', $trend);

        if ($aeroKm > 0.0 && $easyTarget > 0) {
            $avgAero = (int) round($aeroPaceKm / $aeroKm);
            if ($avgAero < $easyTarget - 8) {
                $lines[] = sprintf(
                    '- ⚠️ Allure facile TROP RAPIDE : ses footings tournent à ~%s/km alors que sa cible facile (E) est %s/km → il court trop vite en facile (cause d’un 80/20 déséquilibré). À corriger : imposer l’allure E.',
                    self::pace($avgAero),
                    self::pace($easyTarget),
                );
            } else {
                $lines[] = sprintf('- Allure facile bien dosée (~%s/km, cible %s/km).', self::pace($avgAero), self::pace($easyTarget));
            }
        }

        $lines[] = sprintf('- Plus longue sortie récente : %.1f km.', $longest);

        if ($fitness !== null) {
            $load = TrainingLoadView::build($tenantId, $fitness, $today, new TrainingLoadCalculator());
            if (($load['hasData'] ?? false) === true) {
                $zones = is_array($load['zones'] ?? null) ? $load['zones'] : [];
                $total = (int) ($zones['total'] ?? 0);
                $easyPct = $total > 0 ? (int) round(100 * (int) ($zones['easy'] ?? 0) / $total) : 0;
                $charge = ($load['reliable'] ?? true) === true
                    ? sprintf('fitness %d, forme %+d, ratio %s', (int) ($load['fitness'] ?? 0), (int) ($load['form'] ?? 0), number_format((float) ($load['acwr'] ?? 0), 2, ',', ''))
                    : 'en calibration (peu d’historique — ne pas surinterpréter)';
                $lines[] = sprintf('- Équilibre : ~%d%% du temps en facile (cible 80%%) ; charge : %s.', $easyPct, $charge);

                $adaptation = AdaptationView::build($tenantId, $load, $today, new AdaptationAnalyzer());
                if ($adaptation !== null) {
                    $lines[] = 'RECOMMANDATION MOTEUR : '.$adaptation['headline'].' — '.implode(' ; ', $adaptation['suggestions']).'.';
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
