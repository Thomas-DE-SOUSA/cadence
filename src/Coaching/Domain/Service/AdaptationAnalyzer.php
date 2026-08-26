<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Service;

use Cadence\Coaching\Domain\ValueObject\AdaptationReport;

/**
 * Turns the week's signals (compliance, acute:chronic load, form, 80/20 balance)
 * into a single actionable recommendation. Deterministic rules, priority order:
 * safety (deload) > balance (rebalance) > progress > hold.
 */
final class AdaptationAnalyzer
{
    public function analyze(int $doneCount, int $plannedCount, float $acwr, int $tsb, int $easyPct): AdaptationReport
    {
        $compliance = $plannedCount > 0 ? (int) round(100 * $doneCount / $plannedCount) : 0;

        $reasons = [];
        $reasons[] = "Assiduité : {$doneCount}/{$plannedCount} séances ({$compliance}%)";
        if ($acwr > 0.0) {
            $reasons[] = 'Ratio de charge '.number_format($acwr, 2, ',', '');
        }
        $reasons[] = 'Forme '.($tsb > 0 ? '+' : '').$tsb;
        if ($easyPct > 0) {
            $reasons[] = "{$easyPct}% facile".($easyPct < 75 ? ' (sous 80/20)' : '');
        }

        $overloaded = $acwr > 1.4 || $tsb < -25 || ($plannedCount > 0 && $compliance < 55);
        $tooIntense = $easyPct > 0 && $easyPct < 72;
        $fresh = $compliance >= 85 && $acwr >= 0.8 && $acwr <= 1.3 && ($easyPct === 0 || $easyPct >= 75);

        if ($overloaded) {
            return new AdaptationReport(
                'deload',
                'Lève le pied cette semaine',
                $reasons,
                [
                    'Réduire le volume d’environ 15–20 %',
                    'Garder une seule séance de qualité, plus légère',
                    'Le reste en endurance vraiment facile',
                ],
                "L'athlète montre des signes de surcharge (ratio de charge élevé, forme très négative ou assiduité en baisse). "
                ."Génère une semaine d'allègement : volume réduit d'environ 15–20 %, une seule séance de qualité légère, "
                .'le reste en endurance facile, et davantage de récupération.',
            );
        }

        if ($tooIntense) {
            return new AdaptationReport(
                'rebalance',
                'Rééquilibre vers plus de facile',
                $reasons,
                [
                    'Ralentir nettement les footings (allure E)',
                    'Éviter la « zone grise » (ni facile ni dur)',
                    'Garder 1–2 séances de qualité franches',
                ],
                "L'athlète court trop en intensité (moins de 72 % du temps en facile). "
                .'Génère une semaine plus polarisée : environ 80 % en endurance vraiment facile, '
                .'1 à 2 séances de qualité nettes, et supprime la zone grise (allures intermédiaires).',
            );
        }

        if ($fresh) {
            return new AdaptationReport(
                'progress',
                'Tu peux progresser',
                $reasons,
                [
                    'Augmenter le volume d’environ 5–10 %',
                    'Allonger un peu la sortie longue',
                    'Densifier prudemment la qualité',
                ],
                "L'athlète est assidu, bien récupéré et bien équilibré. "
                .'Génère une semaine de progression : +5 à 10 % de volume, sortie longue légèrement plus longue, '
                .'qualité maintenue voire densifiée prudemment (surcharge progressive).',
            );
        }

        return new AdaptationReport(
            'hold',
            'Consolide',
            $reasons,
            [
                'Maintenir le volume actuel',
                'Viser la régularité et la qualité d’exécution',
            ],
            "L'athlète est sur une bonne trajectoire sans signal fort. "
            .'Génère une semaine de consolidation : volume stable, structure identique, '
            ."focalisée sur la régularité et la qualité d'exécution.",
        );
    }
}
