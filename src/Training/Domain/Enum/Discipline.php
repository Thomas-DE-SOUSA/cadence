<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Enum;

/**
 * The training discipline of a program's objective. It drives *how* the coach
 * reasons: road prep is pace-based (VDOT zones), trail/ultra are about vertical
 * gain, time-on-feet and effort — not pace/km. Inferred from the objective
 * (distance + race/goal wording).
 */
enum Discipline: string
{
    case ROUTE_10K = 'ROUTE_10K';
    case ROUTE_SEMI = 'ROUTE_SEMI';
    case ROUTE_MARATHON = 'ROUTE_MARATHON';
    case TRAIL = 'TRAIL';
    case ULTRA_TRAIL = 'ULTRA_TRAIL';

    public function label(): string
    {
        return match ($this) {
            self::ROUTE_10K => 'Route — 5/10 km',
            self::ROUTE_SEMI => 'Route — semi-marathon',
            self::ROUTE_MARATHON => 'Route — marathon',
            self::TRAIL => 'Trail',
            self::ULTRA_TRAIL => 'Ultra-trail',
        };
    }

    /** Road disciplines drive off pace; trail/ultra treat pace as secondary. */
    public function usesPace(): bool
    {
        return match ($this) {
            self::TRAIL, self::ULTRA_TRAIL => false,
            default => true,
        };
    }

    /** The coaching directives injected into the planner prompt for this discipline. */
    public function playbook(): string
    {
        return match ($this) {
            self::ROUTE_10K => <<<'TXT'
                COURSE SUR ROUTE, 5–10 km. Leviers prioritaires : le SEUIL (allure T, cruise intervals) et la VO2max / allure spécifique 10 km (allure I et allure course). Volume modéré, polarisé 80/20. Sortie longue utile mais ≤ ~90 min. Ajoute des lignes droites (strides) pour l'économie de course. Séances décrites en allures précises (min/km).
                TXT,
            self::ROUTE_SEMI => <<<'TXT'
                SEMI-MARATHON sur route. Leviers : SEUIL en volume (cruise intervals longs, tempo continu) et allure spécifique semi ; sortie longue avec portions à allure marathon/semi ; VO2max en entretien. Volume plus élevé qu'au 10 km. Séances en allures (min/km).
                TXT,
            self::ROUTE_MARATHON => <<<'TXT'
                MARATHON sur route. Leviers : VOLUME et endurance, allure MARATHON (M) spécifique, seuil, et SORTIE LONGUE PROGRESSIVE jusqu'à 30–35 km avec fin à allure marathon. Travaille la nutrition/hydratation sur les longues. VO2max en entretien léger. Séances en allures (min/km).
                TXT,
            self::TRAIL => <<<'TXT'
                TRAIL (relief). Raisonne en DÉNIVELÉ (D+) et en TEMPS D'EFFORT autant qu'en allure : sur terrain, l'allure/km ne veut plus dire grand-chose (utilise l'effort et le GAP). Intègre des CÔTES (montées en seuil/VO2, MARCHE ACTIVE assumée quand ça pousse), de la DESCENTE technique (spécifique, protège les quadriceps) et du RENFORCEMENT. Sortie longue avec D+ significatif. Décris les séances en DURÉE + D+ + effort, l'allure/km est secondaire.
                TXT,
            self::ULTRA_TRAIL => <<<'TXT'
                ULTRA-TRAIL (type UTMB). PRIORITÉ ABSOLUE au TEMPS D'EFFORT (time-on-feet) et au D+ hebdomadaire — PAS aux allures/km. Piliers : gros volume en endurance fondamentale ; SORTIES LONGUES ENCHAÎNÉES le week-end (back-to-back : longue samedi + longue dimanche) ; MARCHE ACTIVE en côte, entraînée et assumée ; D+ spécifique au terrain de la course ; longues descentes (dégâts musculaires) ; NUTRITION/HYDRATATION et gestion de la NUIT/du sommeil ; RENFORCEMENT (chevilles, quadriceps, gainage). Peu de VO2max : du seuil en côte suffit. Décris les séances en DURÉE (durationSeconds) + D+ + effort dans la note ; laisse paceSecondsPerKm à null la plupart du temps.
                TXT,
        };
    }

    /** Best-guess discipline from the objective wording + its distance. */
    public static function infer(string $text, ?int $distanceMeters): self
    {
        $t = mb_strtolower($text);
        $km = $distanceMeters !== null ? $distanceMeters / 1000 : 0.0;

        $has = static fn (string ...$needles): bool => array_reduce(
            $needles,
            static fn (bool $carry, string $n): bool => $carry || str_contains($t, $n),
            false,
        );

        if ($km >= 45 || $has('ultra', 'utmb', 'ccc', 'tds', 'occ', '100 km', '100km', '80 km', '80km', 'miles', '6000', '100 miles')) {
            return self::ULTRA_TRAIL;
        }
        if ($has('trail', 'd+', 'dénivelé', 'denivele', 'kv', 'vertical', 'skyrace', 'montagne', 'sentier')) {
            return self::TRAIL;
        }
        if ($has('semi', 'half') || ($km >= 19 && $km < 25)) {
            return self::ROUTE_SEMI;
        }
        if (($has('marathon') && ! $has('semi')) || ($km >= 40 && $km < 45)) {
            return self::ROUTE_MARATHON;
        }

        return self::ROUTE_10K;
    }
}
