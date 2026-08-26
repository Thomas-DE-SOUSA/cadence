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

        $phaseBlock = trim($c->blueprint) === '' ? '' : <<<PHASE

        Cette phase du plan expert est ta BASE — adapte-la (volume, intensité) au ressenti et aux performances, sans en changer la nature :
        - Phase : {$c->phaseName} — {$c->phaseFocus}
        - Plan de référence :
        {$c->blueprint}
        PHASE;

        $pacesBlock = trim($c->athletePaces) === '' ? '' : "\n        - Allures de l'athlète (À UTILISER pour toutes les allures cibles) : {$c->athletePaces}";

        $stateBlock = trim($c->athleteState) === '' ? '' : <<<STATE


        ÉTAT ACTUEL DE L'ATHLÈTE — à garder EN PERMANENCE en tête pour CHAQUE séance :
        {$c->athleteState}
        STATE;

        return <<<PROMPT
        Conçois le PROCHAIN cycle d'entraînement, jour par jour.

        🎯 OBJECTIF PRIORITAIRE (ne le perds JAMAIS de vue) : {$c->goal}
        Course cible : {$c->targetRaceName} (date : {$c->targetRaceDate}). Chaque séance doit rapprocher de cet objectif.

        Contexte :
        - Début du cycle : {$c->startDate}, durée : {$c->weeks} semaine(s) ({$days} jours).
        - Ressenti de l'athlète : {$c->ressenti}
        - Performances récentes (assignées) : {$c->recentPerformance}{$pacesBlock}
        - {$c->previousCycle}{$phaseBlock}{$stateBlock}

        Réponds avec UNIQUEMENT un objet JSON (aucun texte, aucune balise markdown) de cette forme EXACTE :
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

        Règles :
        - Une entrée par jour pour les {$days} jours (inclure les jours de repos avec type REST, sans steps).
        - DÉCOUPE chaque séance qui court en `steps` : échauffement, corps (blocs répétés avec récup), retour au calme. Un footing continu = 1 seul step (durée + allure). Un jour de repos = steps vide.
        - Utilise IMPÉRATIVEMENT les allures de l'athlète ci-dessus pour chaque `paceSecondsPerKm`. N'invente pas d'allures.
        - Progression cohérente, principe hard/easy, majorité du volume en E. Allures en secondes par km (ex. 4:00/km = 240).
        - ADAPTE le volume et l'intensité à l'ÉTAT ACTUEL ci-dessus (dernières sorties réelles, forme, recommandation) — ne planifie jamais « dans le vide », et sers toujours l'objectif prioritaire.
        PROMPT;
    }
}
