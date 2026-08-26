<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Ai;

use Cadence\Shared\Infrastructure\Ai\GeminiClient;
use Cadence\Training\Domain\Port\CyclePlanner;
use Cadence\Training\Domain\ValueObject\PlannedCycle;
use Cadence\Training\Domain\ValueObject\PlannedSessionData;
use Cadence\Training\Domain\ValueObject\PlannerContext;
use Cadence\Training\Domain\ValueObject\SessionStep;
use RuntimeException;

/**
 * Designs a training cycle with Gemini (free tier), day by day, adapting to the
 * athlete's feedback and the previous cycle. Returns a validated structured plan.
 */
final class GeminiCyclePlanner implements CyclePlanner
{
    public function __construct(private readonly GeminiClient $client)
    {
    }

    public function plan(PlannerContext $context): PlannedCycle
    {
        $text = $this->client->complete(
            "Tu es un coach expert en course à pied. Tu réponds UNIQUEMENT avec l'objet JSON demandé.",
            $this->prompt($context),
            ['responseMimeType' => 'application/json', 'maxOutputTokens' => 16384],
        );

        return $this->toPlannedCycle($this->decode($text));
    }

    /** @return array<string, mixed> */
    private function decode(string $text): array
    {
        $clean = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
        $decoded = json_decode(trim($clean), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("L'IA n'a pas renvoyé de plan valide.");
        }

        return $decoded;
    }

    /** @param array<string, mixed> $d */
    private function toPlannedCycle(array $d): PlannedCycle
    {
        $sessions = [];
        foreach (is_array($d['sessions'] ?? null) ? $d['sessions'] : [] as $s) {
            if (is_array($s)) {
                $sessions[] = new PlannedSessionData(
                    (int) ($s['dayOffset'] ?? 0),
                    (string) ($s['type'] ?? 'EASY'),
                    (string) ($s['title'] ?? ''),
                    (string) ($s['description'] ?? ''),
                    isset($s['targetDistanceMeters']) ? (int) $s['targetDistanceMeters'] : null,
                    isset($s['targetDurationSeconds']) ? (int) $s['targetDurationSeconds'] : null,
                    isset($s['targetPaceSecondsPerKm']) ? (int) $s['targetPaceSecondsPerKm'] : null,
                    $this->toSteps($s['steps'] ?? null),
                );
            }
        }

        return new PlannedCycle(
            (string) ($d['name'] ?? 'Cycle'),
            (string) ($d['focus'] ?? ''),
            array_values($sessions),
        );
    }

    /**
     * @param mixed $raw
     *
     * @return list<SessionStep>
     */
    private function toSteps($raw): array
    {
        $steps = [];
        foreach (is_array($raw) ? $raw : [] as $st) {
            if (is_array($st)) {
                $steps[] = new SessionStep(
                    (string) ($st['label'] ?? ''),
                    isset($st['repeat']) ? max(1, (int) $st['repeat']) : 1,
                    isset($st['distanceMeters']) && $st['distanceMeters'] !== null ? (int) $st['distanceMeters'] : null,
                    isset($st['durationSeconds']) && $st['durationSeconds'] !== null ? (int) $st['durationSeconds'] : null,
                    isset($st['paceSecondsPerKm']) && $st['paceSecondsPerKm'] !== null ? (int) $st['paceSecondsPerKm'] : null,
                    isset($st['recoverySeconds']) && $st['recoverySeconds'] !== null ? (int) $st['recoverySeconds'] : null,
                    (string) ($st['note'] ?? ''),
                );
            }
        }

        return array_values($steps);
    }

    private function prompt(PlannerContext $c): string
    {
        $days = $c->weeks * 7;

        $pacesLine = trim($c->athletePaces) === ''
            ? '(non renseignées — reste cohérent avec un coureur de ce niveau)'
            : $c->athletePaces;

        $base = trim($c->blueprint) === '' ? '' :
            "\n\n## Plan expert de la phase — base à adapter (garde sa NATURE, ajuste volume/intensité)\n"
            ."Phase : {$c->phaseName} — {$c->phaseFocus}\n{$c->blueprint}";

        $state = trim($c->athleteState) === '' ? '' :
            "\n\n## État actuel de l'athlète — ANALYSE à respecter pour CHAQUE séance\n{$c->athleteState}";

        return <<<PROMPT
        Tu es le coach personnel de cet athlète. Conçois son PROCHAIN cycle d'entraînement, jour par jour, et réponds en JSON strict.

        ## Objectif — priorité absolue (ne le perds JAMAIS de vue)
        {$c->goal}
        Course cible : {$c->targetRaceName} (le {$c->targetRaceDate}). CHAQUE séance doit rapprocher de cet objectif.

        ## Cadre du cycle
        - Démarre le {$c->startDate} · {$c->weeks} semaine(s) = {$days} jours → EXACTEMENT une entrée par jour.
        - Ressenti saisi par l'athlète : {$c->ressenti}
        - Allures perso (à utiliser telles quelles, ne JAMAIS inventer) : {$pacesLine}
        - {$c->previousCycle}{$base}{$state}

        ## Format de sortie — réponds UNIQUEMENT avec cet objet JSON (aucun texte, aucune balise markdown)
        {
          "name": "nom court du cycle (ex. C2 Développement)",
          "focus": "1 phrase sur l'objectif du cycle",
          "sessions": [
            {
              "dayOffset": 0,
              "type": "EASY|LONG|THRESHOLD|INTERVALS|RECOVERY|RACE_PACE|CROSS|REST",
              "title": "titre court",
              "description": "résumé court de la séance en 1 phrase",
              "targetDistanceMeters": int|null,
              "targetDurationSeconds": int|null,
              "targetPaceSecondsPerKm": int|null,
              "steps": [
                {
                  "label": "Échauffement|Seuil|Fractionné|Bloc|Récup|Retour au calme|Lignes droites|…",
                  "repeat": 1,
                  "distanceMeters": int|null,
                  "durationSeconds": int|null,
                  "paceSecondsPerKm": int|null,
                  "recoverySeconds": int|null,
                  "note": "précision courte, ex. 'progressif'"
                }
              ]
            }
          ]
        }

        ## Règles (impératives)
        - EXACTEMENT une entrée par jour sur {$days} jours ; jour de repos = type REST avec `steps` vide.
        - Découpe chaque séance courue en `steps` : échauffement → corps (blocs répétés avec récup) → retour au calme. Un footing continu = 1 seul step. `repeat` pour les blocs (ex. 5×1000 m → repeat 5).
        - `paceSecondsPerKm` = TOUJOURS une des allures perso ci-dessus, en secondes/km (ex. 4:00/km = 240). N'invente aucune allure.
        - Progression cohérente, alternance dur/facile, ~80 % du volume en facile (E) et une minorité vraiment dure (polarisé, pas de zone grise).
        - Adapte volume et intensité à l'ANALYSE ci-dessus (dernières sorties, forme, reco) ; ne planifie jamais « dans le vide » et sers toujours l'objectif.
        PROMPT;
    }
}
