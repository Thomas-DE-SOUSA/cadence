<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Plan;

use Cadence\Training\Domain\Enum\SessionType;

/**
 * The library of ready-made expert plans. Pure data — pace roles are expressed
 * as seconds per kilometre and durations as seconds.
 */
final class TrainingPlanCatalog
{
    /** @return list<TrainingPlan> */
    public static function all(): array
    {
        return [
            self::sub40(),
            self::halfMarathon145(),
            self::discover10k(),
        ];
    }

    public static function byKey(string $key): ?TrainingPlan
    {
        foreach (self::all() as $plan) {
            if ($plan->key === $key) {
                return $plan;
            }
        }

        return null;
    }

    private static function sub40(): TrainingPlan
    {
        return new TrainingPlan(
            'sub40-10k',
            '10 km sous 40 min',
            '9 semaines, 5 séances/semaine. Fondation, développement VMA/seuil puis allure spécifique 4:00/km.',
            'Passer sous 40:00 au 10 km',
            'Odysséa Paris 10 km',
            5,
            [
                new PlanPhase('Fondation', 'Construire l\'endurance de base et réveiller la vitesse.', 3, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::EASY, 'Footing + lignes droites', '45 min souple + 6 lignes droites (accélérations 80 m).', null, 2700, 315),
                    new PlanSessionTemplate(2, SessionType::RECOVERY, 'Footing récupération', '35 min très souple, décontracté.', null, 2100, 340),
                    new PlanSessionTemplate(3, SessionType::THRESHOLD, 'Seuil 2×8\'', '15\' échauffement, 2×8\' au seuil (récup 3\'), 10\' retour au calme.', null, null, 250),
                    self::rest(4),
                    new PlanSessionTemplate(5, SessionType::EASY, 'Footing', '50 min en aisance respiratoire.', null, 3000, 315),
                    new PlanSessionTemplate(6, SessionType::LONG, 'Sortie longue', '1h15 en endurance fondamentale.', null, 4500, 300),
                ]),
                new PlanPhase('Développement', 'Élever la VMA et le seuil, densifier le volume.', 3, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::INTERVALS, 'VMA 10×400 m', '15\' échauffement, 10×400 m à 3:40/km (récup 1\'), 10\' retour.', 400, null, 220),
                    new PlanSessionTemplate(2, SessionType::EASY, 'Footing', '45 min souple.', null, 2700, 315),
                    new PlanSessionTemplate(3, SessionType::THRESHOLD, 'Seuil 3×8\'', '3×8\' au seuil (récup 2\'30).', null, null, 250),
                    self::rest(4),
                    new PlanSessionTemplate(5, SessionType::EASY, 'Footing + lignes', '45 min + 6 lignes droites.', null, 2700, 315),
                    new PlanSessionTemplate(6, SessionType::LONG, 'Sortie longue', '1h25 avec 20\' à allure marathon en fin.', null, 5100, 300),
                ]),
                new PlanPhase('Affûtage', 'Ancrer l\'allure spécifique 10 km (4:00/km).', 2, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::RACE_PACE, 'Allure 10K 4×1500 m', '4×1500 m à 4:00/km (récup 2\'30).', 1500, null, 240),
                    new PlanSessionTemplate(2, SessionType::EASY, 'Footing', '40 min souple.', null, 2400, 315),
                    new PlanSessionTemplate(3, SessionType::THRESHOLD, 'Seuil 2×2000 m', '2×2000 m à 4:08/km (récup 3\').', 2000, null, 248),
                    self::rest(4),
                    new PlanSessionTemplate(5, SessionType::EASY, 'Footing + lignes', '35 min + 5 lignes droites.', null, 2100, 315),
                    new PlanSessionTemplate(6, SessionType::LONG, 'Sortie longue légère', '1h05 en endurance.', null, 3900, 300),
                ]),
                new PlanPhase('Taper', 'Fraîcheur : réduire le volume, garder le rythme.', 1, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::RACE_PACE, 'Allure 10K 3×1000 m', '3×1000 m à 4:00/km (récup 2\'), sensations.', 1000, null, 240),
                    new PlanSessionTemplate(2, SessionType::EASY, 'Footing court', '30 min souple.', null, 1800, 315),
                    new PlanSessionTemplate(3, SessionType::EASY, 'Déblocage + lignes', '25 min + 4 lignes droites.', null, 1500, 315),
                    self::rest(4),
                    new PlanSessionTemplate(5, SessionType::RECOVERY, 'Réveil musculaire', '20 min très léger + 3 accélérations.', null, 1200, 340),
                    new PlanSessionTemplate(6, SessionType::RACE, 'Odysséa 10 km', 'Course : viser 4:00/km réguliers, sous 40:00.', 10000, null, 240),
                ]),
            ],
        );
    }

    private static function halfMarathon145(): TrainingPlan
    {
        return new TrainingPlan(
            'half-145',
            'Semi-marathon 1h45',
            '10 semaines, 4 séances/semaine. Endurance, seuil et allure spécifique 4:58/km.',
            'Courir le semi-marathon en 1h45',
            'Semi-marathon',
            4,
            [
                new PlanPhase('Fondation', 'Bâtir le foncier et le seuil.', 4, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::EASY, 'Footing', '45 min souple.', null, 2700, 345),
                    self::rest(2),
                    new PlanSessionTemplate(3, SessionType::THRESHOLD, 'Tempo 2×10\'', '2×10\' à allure seuil (récup 3\').', null, null, 285),
                    self::rest(4),
                    new PlanSessionTemplate(5, SessionType::EASY, 'Footing + lignes', '40 min + 5 lignes droites.', null, 2400, 345),
                    new PlanSessionTemplate(6, SessionType::LONG, 'Sortie longue', '1h20 en endurance.', null, 4800, 320),
                ]),
                new PlanPhase('Développement', 'VMA longue et volume de seuil.', 3, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::INTERVALS, 'VMA 8×500 m', '8×500 m à 4:10/km (récup 1\'15).', 500, null, 250),
                    self::rest(2),
                    new PlanSessionTemplate(3, SessionType::THRESHOLD, 'Seuil 3×10\'', '3×10\' au seuil (récup 2\').', null, null, 285),
                    self::rest(4),
                    new PlanSessionTemplate(5, SessionType::EASY, 'Footing', '45 min souple.', null, 2700, 345),
                    new PlanSessionTemplate(6, SessionType::LONG, 'Sortie longue', '1h35 dont 25\' à allure semi.', null, 5700, 320),
                ]),
                new PlanPhase('Spécifique', 'Ancrer l\'allure semi (4:58/km).', 2, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::RACE_PACE, 'Allure semi 4×2 km', '4×2 km à 4:58/km (récup 2\'30).', 2000, null, 298),
                    self::rest(2),
                    new PlanSessionTemplate(3, SessionType::THRESHOLD, 'Seuil 2×15\'', '2×15\' au seuil (récup 3\').', null, null, 285),
                    self::rest(4),
                    new PlanSessionTemplate(5, SessionType::EASY, 'Footing + lignes', '40 min + lignes droites.', null, 2400, 345),
                    new PlanSessionTemplate(6, SessionType::LONG, 'Sortie longue spécifique', '1h40 dont 40\' à allure semi.', null, 6000, 320),
                ]),
                new PlanPhase('Taper', 'Affûtage final avant la course.', 1, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::RACE_PACE, 'Allure semi 3×2 km', '3×2 km à 4:58/km (récup 2\').', 2000, null, 298),
                    self::rest(2),
                    new PlanSessionTemplate(3, SessionType::EASY, 'Déblocage', '30 min + 4 lignes droites.', null, 1800, 345),
                    self::rest(4),
                    new PlanSessionTemplate(5, SessionType::RECOVERY, 'Réveil musculaire', '20 min léger.', null, 1200, 375),
                    new PlanSessionTemplate(6, SessionType::RACE, 'Semi objectif', 'Course : 4:58/km réguliers, viser 1h45.', 21100, null, 298),
                ]),
            ],
        );
    }

    private static function discover10k(): TrainingPlan
    {
        return new TrainingPlan(
            'discover-10k',
            'Découverte 10 km',
            '8 semaines, 3 séances/semaine. Progressif, pour finir son premier 10 km en aisance.',
            'Terminer un 10 km en aisance',
            '10 km',
            3,
            [
                new PlanPhase('Base', 'S\'habituer à courir régulièrement.', 4, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::EASY, 'Footing', '30 min en aisance.', null, 1800, 390),
                    self::rest(2),
                    new PlanSessionTemplate(3, SessionType::EASY, 'Course / marche', '5×(4\' course / 1\' marche).', null, null, 390),
                    self::rest(4),
                    self::rest(5),
                    new PlanSessionTemplate(6, SessionType::LONG, 'Sortie longue douce', '45 min souple.', null, 2700, 375),
                ]),
                new PlanPhase('Progression', 'Allonger et introduire du rythme.', 3, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::EASY, 'Footing', '35 min régulier.', null, 2100, 390),
                    self::rest(2),
                    new PlanSessionTemplate(3, SessionType::THRESHOLD, 'Tempo 2×8\'', '2×8\' à allure soutenue (récup 3\').', null, null, 345),
                    self::rest(4),
                    self::rest(5),
                    new PlanSessionTemplate(6, SessionType::LONG, 'Sortie longue', '55 min en endurance.', null, 3300, 375),
                ]),
                new PlanPhase('Objectif', 'Se rapprocher de l\'allure course.', 1, [
                    self::rest(0),
                    new PlanSessionTemplate(1, SessionType::RACE_PACE, 'Allure course 3×1 km', '3×1 km à 6:00/km (récup 2\').', 1000, null, 360),
                    self::rest(2),
                    new PlanSessionTemplate(3, SessionType::EASY, 'Footing court', '25 min léger.', null, 1500, 390),
                    self::rest(4),
                    self::rest(5),
                    new PlanSessionTemplate(6, SessionType::RACE, '10 km objectif', 'Course : finir en aisance, ~6:00/km.', 10000, null, 360),
                ]),
            ],
        );
    }

    private static function rest(int $dayInWeek): PlanSessionTemplate
    {
        return new PlanSessionTemplate($dayInWeek, SessionType::REST, 'Repos', 'Récupération complète.');
    }
}
