<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Service;

use Cadence\Coaching\Domain\ValueObject\ReadinessLevel;
use Cadence\Coaching\Domain\ValueObject\ReadinessScore;
use Cadence\Coaching\Domain\ValueObject\WellnessCheckIn;

/**
 * Turns a subjective check-in into a readiness verdict. Sensations set the base
 * score; pain is an override — a limiting pain forces red no matter how good the
 * rest feels, because training through it risks injury (Pure — no side effects).
 */
final class ReadinessAssessor
{
    public function assess(WellnessCheckIn $c): ReadinessScore
    {
        $average = ($c->sleep + $c->energy + $c->legs + $c->motivation) / 4.0;
        $score = (int) round(($average - 1) / 4 * 100); // 1..5 → 0..100

        // A pain that limits running trumps everything: hard red.
        if ($c->painLevel >= 3) {
            $where = $c->painLocation !== '' ? ' ('.$c->painLocation.')' : '';

            return new ReadinessScore(
                min($score, 25),
                ReadinessLevel::RED,
                'Douleur qui limite',
                'Une douleur'.$where.' t’empêche de courir normalement : priorité repos et soin, pas de séance dure aujourd’hui. Si ça persiste 3–4 jours, fais-toi voir.',
            );
        }

        // A moderate pain can't read better than amber.
        if ($c->painLevel === 2) {
            $score = min($score, 55);
        }

        $level = $score >= 70 ? ReadinessLevel::GREEN : ($score >= 45 ? ReadinessLevel::AMBER : ReadinessLevel::RED);

        [$headline, $advice] = match ($level) {
            ReadinessLevel::GREEN => ['En forme', 'Bonnes sensations : tu peux enchaîner la séance prévue, qualité comprise.'],
            ReadinessLevel::AMBER => ['Sensations moyennes', 'Reste prudent : privilégie le facile et réduis l’intensité si le corps ne suit pas.'],
            ReadinessLevel::RED => ['Fatigue marquée', 'Allège nettement : footing très cool, récupération ou repos. Ne force pas aujourd’hui.'],
        };

        if ($c->painLevel === 2) {
            $where = $c->painLocation !== '' ? ' ('.$c->painLocation.')' : '';
            $advice .= ' Surveille ta gêne'.$where.' — stoppe si elle s’aggrave.';
        }

        return new ReadinessScore($score, $level, $headline, $advice);
    }
}
