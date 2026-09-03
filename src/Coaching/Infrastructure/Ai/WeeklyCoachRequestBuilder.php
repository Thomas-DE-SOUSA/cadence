<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Ai;

use Cadence\Coaching\Domain\Enum\MessageRole;
use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\ValueObject\FitnessSnapshot;
use Cadence\Coaching\Domain\ValueObject\StrengthWeekSummary;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewContext;
use Cadence\Coaching\Infrastructure\Knowledge\CoachingKnowledge;

/** Assembles the cross-modal (running + strength) system prompt for the weekly coach. */
final class WeeklyCoachRequestBuilder
{
    public function __construct(private readonly CoachingKnowledge $knowledge)
    {
    }

    public function system(WeeklyReviewContext $context): string
    {
        $sections = [
            $this->knowledge->weeklyFoundation(),
            "# L'athlète et l'objectif\n".$this->athleteBlock($context),
            "# La semaine — course à pied\n".$this->runningBlock($context),
            "# La semaine — muscu\n".$this->strengthBlock($context->strength),
            $this->rules(),
        ];

        return implode("\n\n", $sections);
    }

    /**
     * @param list<Message> $history
     *
     * @return list<array{role:string,content:string}>
     */
    public function messages(array $history): array
    {
        $messages = [];
        foreach ($history as $message) {
            $messages[] = [
                'role' => $message->role === MessageRole::ATHLETE ? 'user' : 'assistant',
                'content' => $message->text,
            ];
        }

        return $messages;
    }

    private function athleteBlock(WeeklyReviewContext $c): string
    {
        $lines = [
            "- Semaine analysée : du {$c->weekStart} au {$c->weekEnd} (lundi→dimanche).",
            "- Objectif : {$c->goal}",
            '- Course cible : '.($c->targetRaceName !== '' ? $c->targetRaceName : 'non précisée').($c->targetRaceDate !== null ? " (le {$c->targetRaceDate})" : ''),
        ];

        if ($c->fitness instanceof FitnessSnapshot) {
            $p = $c->fitness->paces;
            $lines[] = sprintf('- VDOT estimé : %.1f. Allures perso (s/km) : E %d, M %d, T %d, I %d, R %d.', $c->fitness->vdot, $p->easy, $p->marathon, $p->threshold, $p->interval, $p->repetition);
        }

        return implode("\n", $lines);
    }

    private function runningBlock(WeeklyReviewContext $c): string
    {
        $lines = ['- Sorties récentes : '.($c->recentRunsSummary !== '' ? $c->recentRunsSummary : 'aucune enregistrée.')];
        if (trim($c->runningAnalysis) !== '') {
            $lines[] = $c->runningAnalysis;
        }

        return implode("\n", $lines);
    }

    private function strengthBlock(StrengthWeekSummary $s): string
    {
        if (! $s->hasData()) {
            return '- Aucune séance de muscu enregistrée cette semaine ni la précédente.';
        }

        $lines = [
            sprintf('- Séances : %d cette semaine (%d la précédente).', $s->sessions, $s->prevSessions),
            sprintf('- Volume soulevé : %s kg (%s kg la semaine précédente).', $this->kg($s->tonnageKg), $this->kg($s->prevTonnageKg)),
            sprintf('- Séries de travail : %d. Séances sollicitant les jambes : %d.', $s->workingSets, $s->legSessions),
        ];

        if ($s->daysSinceLast !== null) {
            $lines[] = sprintf('- Dernière séance muscu il y a %d jour(s).', $s->daysSinceLast);
        }

        if ($s->muscleBalance !== []) {
            $parts = [];
            foreach (array_slice($s->muscleBalance, 0, 6) as $m) {
                $parts[] = "{$m['label']} {$m['sets']}";
            }
            $lines[] = '- Répartition (séries par muscle) : '.implode(', ', $parts).'.';
        }

        if ($s->weightAvgKg !== null) {
            $trend = $s->weightPrevAvgKg !== null
                ? sprintf(' (%+.1f kg vs semaine précédente à %.1f kg)', $s->weightAvgKg - $s->weightPrevAvgKg, $s->weightPrevAvgKg)
                : '';
            $lines[] = sprintf('- Poids moyen : %.1f kg%s.', $s->weightAvgKg, $trend);
        }

        return implode("\n", $lines);
    }

    private function kg(float $value): string
    {
        return number_format($value, $value === floor($value) ? 0 : 1, ',', ' ');
    }

    private function rules(): string
    {
        return <<<RULES
        # Ta mission — le bilan de la semaine
        - Juge la semaine DANS SON ENSEMBLE, en croisant course et muscu, au service de l'objectif de course.
        - Réponds en français, comme le coach personnel de cet athlète : concis, chaleureux, direct, jamais générique.
        - Structure ta réponse en Markdown : un titre-verdict clair (bonne semaine / à maintenir / à rééquilibrer / lever le pied), puis 1-3 raisons qui comptent vraiment, puis l'action concrète pour la semaine prochaine.
        - Appuie-toi UNIQUEMENT sur ses données ci-dessus ; n'invente jamais de chiffres.
        - La course est prioritaire : la muscu la soutient, elle ne doit pas voler la récup des séances clés. Regarde surtout le placement et la charge jambes, pas le tonnage brut.
        - Les changements muscu sont des CONSEILS (fréquence, placement des jours jambes, retirer une séance) — il n'y a pas de programme muscu planifié à réécrire.
        - Sécurité : tout drapeau rouge (douleur vive/localisée, sous-alimentation avec chute de poids + fatigue) → repos / prudence, jamais « coacher à travers ».
        RULES;
    }
}
