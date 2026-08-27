<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Read;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Domain\Service\AdaptationAnalyzer;
use Cadence\Coaching\Domain\Service\ReadinessAssessor;
use Cadence\Coaching\Domain\Service\TrainingLoadCalculator;
use Cadence\Coaching\Domain\ValueObject\FitnessSnapshot;
use Cadence\Coaching\Domain\ValueObject\WellnessCheckIn;
use Cadence\Coaching\Infrastructure\Persistence\Eloquent\WellnessCheckInModel;
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
            $wellness = self::wellnessLine($tenantId, $today);

            return 'ANALYSE : aucune sortie récente enregistrée — démarre prudemment.'
                .($wellness !== '' ? "\n".$wellness : '');
        }

        $lines = ['ANALYSE de l’historique récent (4 semaines) :'];

        // Subjective check-in first — sensations/pain must never be missed by the
        // brain, and a limiting pain trumps every load number below.
        $wellness = self::wellnessLine($tenantId, $today);
        if ($wellness !== '') {
            $lines[] = $wellness;
        }

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

        return implode("\n", $lines);
    }

    /** The athlete's latest subjective check-in (within 3 days), turned into a directive for the brain. */
    private static function wellnessLine(string $tenantId, string $today): string
    {
        $since = (new DateTimeImmutable($today))->modify('-2 days')->format('Y-m-d');

        $model = WellnessCheckInModel::query()
            ->where('tenant_id', $tenantId)
            ->where('check_date', '>=', $since)
            ->orderByDesc('check_date')
            ->first();

        if (! $model instanceof WellnessCheckInModel) {
            return '';
        }

        $checkIn = new WellnessCheckIn(
            $model->check_date,
            $model->sleep,
            $model->energy,
            $model->legs,
            $model->motivation,
            $model->pain_level,
            $model->pain_location,
            $model->note,
        );
        $readiness = (new ReadinessAssessor())->assess($checkIn);

        $when = $checkIn->date === $today ? 'aujourd’hui' : 'récent';
        $sensations = sprintf('sommeil %d/5, énergie %d/5, jambes %d/5, motivation %d/5', $checkIn->sleep, $checkIn->energy, $checkIn->legs, $checkIn->motivation);

        if ($checkIn->limitsRunning()) {
            $where = $checkIn->painLocation !== '' ? ' au niveau : '.$checkIn->painLocation : '';
            return sprintf(
                '- 🚨 RESSENTI %s — DOULEUR LIMITANTE%s. PRIORITÉ ABSOLUE : pas de séance dure, prévois repos/récupération ou allège fortement. Ne planifie AUCUNE intensité tant que ça n’est pas résolu. (%s)',
                $when,
                $where,
                $sensations,
            );
        }

        $pain = match ($checkIn->painLevel) {
            2 => ' Gêne modérée'.($checkIn->painLocation !== '' ? ' ('.$checkIn->painLocation.')' : '').' à surveiller — reste prudent sur l’intensité.',
            1 => ' Petite gêne'.($checkIn->painLocation !== '' ? ' ('.$checkIn->painLocation.')' : '').' signalée.',
            default => '',
        };
        $note = trim($checkIn->note) !== '' ? ' Note de l’athlète : « '.trim($checkIn->note).' ».' : '';

        return sprintf(
            '- RESSENTI %s — readiness %s (%d/100) : %s. %s.%s%s Tiens compte de ces sensations pour doser la charge du jour.',
            $when,
            $readiness->level->label(),
            $readiness->score,
            $sensations,
            $readiness->headline,
            $pain,
            $note,
        );
    }

    private static function pace(int $secondsPerKm): string
    {
        if ($secondsPerKm <= 0) {
            return '—';
        }

        return intdiv($secondsPerKm, 60).':'.str_pad((string) ($secondsPerKm % 60), 2, '0', STR_PAD_LEFT);
    }
}
