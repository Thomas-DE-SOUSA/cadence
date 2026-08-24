<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Ai;

use Cadence\Coaching\Infrastructure\Knowledge\CoachingKnowledge;

/**
 * Builds the system + user prompt for the guest advisory tool ("Conseil"):
 * a one-off assessment of a runner (not the app's owner) with a progression plan.
 */
final class AdvisorPromptBuilder
{
    public function __construct(private readonly CoachingKnowledge $knowledge)
    {
    }

    public function system(): string
    {
        return $this->knowledge->systemFoundation()."\n\n".<<<'TXT'
        # Mission : état des lieux d'un coureur (mode conseil)

        On te demande un DIAGNOSTIC d'un coureur tiers (ce n'est pas l'utilisateur qui te parle,
        mais une personne qu'il souhaite conseiller). À partir des efforts mesurés, du VDOT estimé,
        des projections et du questionnaire, produis un bilan clair et un plan de progression.

        Structure ta réponse en Markdown, exactement avec ces sections :
        ## Profil & niveau
        ## Allures actuelles par distance
        ## Points forts & axes de progrès
        ## Plan de progression (4 à 8 semaines)
        ## Objectif réaliste

        Règles :
        - Appuie-toi sur la doctrine (VDOT, zones E/M/T/I/R, périodisation, charge/récupération, drapeaux rouges).
        - Donne des allures chiffrées en min/km, et des exemples concrets de séances (ex : 6×800 m à l'allure I, r=2').
        - Reste réaliste : gagner 1–2 points de VDOT prend des semaines/mois ; propose un objectif de temps atteignable et une échéance.
        - Si des données manquent, dis clairement ce qu'il faudrait mesurer (ex : un 10 km chronométré).
        - N'invente jamais de chiffres non fournis. Français, ton professionnel et encourageant.
        TXT;
    }

    /**
     * @param array<string, mixed> $profile
     * @param array{efforts:list<array{distanceMeters:int,label:string,seconds:int,paceSeconds:int}>,vdot:float|null,projections:list<array{distanceMeters:int,label:string,seconds:int,paceSeconds:int,measured:bool}>} $assessment
     */
    public function user(array $profile, array $assessment): string
    {
        $lines = ["# Questionnaire\n"];
        foreach ([
            'displayName' => 'Prénom / alias',
            'age' => 'Âge',
            'sex' => 'Sexe',
            'weightKg' => 'Poids (kg)',
            'level' => 'Niveau déclaré',
            'weeklyKm' => 'Volume hebdo actuel (km)',
            'sessionsPerWeek' => 'Séances / semaine',
            'goalDistanceKm' => 'Distance objectif (km)',
            'goalTime' => 'Temps visé',
            'goalDeadline' => 'Échéance',
            'injuries' => 'Blessures / contraintes',
            'notes' => 'Notes libres',
        ] as $key => $label) {
            $value = $profile[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $lines[] = "- {$label} : ".trim($value);
            } elseif (is_int($value) || is_float($value)) {
                $lines[] = "- {$label} : {$value}";
            }
        }

        $lines[] = "\n# Données mesurées";
        if ($assessment['vdot'] !== null) {
            $lines[] = 'VDOT estimé : '.$assessment['vdot'];
        }
        if ($assessment['efforts'] !== []) {
            $lines[] = "\nMeilleurs efforts mesurés (GPX / chronos fournis) :";
            foreach ($assessment['efforts'] as $e) {
                $lines[] = "- {$e['label']} : ".self::mmss($e['seconds']).' ('.self::pace($e['paceSeconds']).')';
            }
        } else {
            $lines[] = 'Aucun effort mesuré fourni — base-toi surtout sur le questionnaire et dis ce qu\'il faudrait chronométrer.';
        }
        if ($assessment['projections'] !== []) {
            $lines[] = "\nProjections (Riegel, à partir du meilleur effort) :";
            foreach ($assessment['projections'] as $p) {
                $tag = $p['measured'] ? ' [mesuré]' : ' [projeté]';
                $lines[] = "- {$p['label']} : ".self::mmss($p['seconds']).' ('.self::pace($p['paceSeconds']).')'.$tag;
            }
        }

        $lines[] = "\nRédige maintenant le diagnostic complet selon la structure demandée.";

        return implode("\n", $lines);
    }

    private static function mmss(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }

    private static function pace(int $secondsPerKm): string
    {
        return sprintf('%d:%02d/km', intdiv($secondsPerKm, 60), $secondsPerKm % 60);
    }
}
