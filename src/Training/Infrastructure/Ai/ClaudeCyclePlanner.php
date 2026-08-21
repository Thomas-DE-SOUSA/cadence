<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Ai;

use Cadence\Training\Domain\Port\CyclePlanner;
use Cadence\Training\Domain\ValueObject\PlannedCycle;
use Cadence\Training\Domain\ValueObject\PlannedSessionData;
use Cadence\Training\Domain\ValueObject\PlannerContext;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Designs a training cycle with Claude, day by day, adapting to the athlete's
 * feedback and the previous cycle. Returns a validated structured plan.
 */
final class ClaudeCyclePlanner implements CyclePlanner
{
    private const MODEL = 'claude-opus-4-8';

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function __construct(private readonly string $apiKey)
    {
    }

    public function plan(PlannerContext $context): PlannedCycle
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('La clé API Anthropic n\'est pas configurée (ANTHROPIC_API_KEY).');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(120)->post(self::ENDPOINT, [
                'model' => self::MODEL,
                'max_tokens' => 8192,
                'messages' => [['role' => 'user', 'content' => $this->prompt($context)]],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('La génération a échoué : '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('La génération a échoué (HTTP '.$response->status().').');
        }

        $text = $response->json('content.0.text');
        if (! is_string($text)) {
            throw new RuntimeException('Réponse inattendue de l\'IA.');
        }

        return $this->toPlannedCycle($this->decode($text));
    }

    /** @return array<string, mixed> */
    private function decode(string $text): array
    {
        $clean = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
        $decoded = json_decode(trim($clean), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('L\'IA n\'a pas renvoyé de plan valide.');
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
                );
            }
        }

        return new PlannedCycle(
            (string) ($d['name'] ?? 'Cycle'),
            (string) ($d['focus'] ?? ''),
            array_values($sessions),
        );
    }

    private function prompt(PlannerContext $c): string
    {
        $days = $c->weeks * 7;

        return <<<PROMPT
        Tu es un coach expert en course à pied. Conçois le PROCHAIN cycle d'entraînement, jour par jour.

        Contexte :
        - Objectif du programme : {$c->goal}
        - Course cible : {$c->targetRaceName} (date : {$c->targetRaceDate})
        - Début du cycle : {$c->startDate}, durée : {$c->weeks} semaine(s) ({$days} jours).
        - Ressenti de l'athlète : {$c->ressenti}
        - Performances récentes : {$c->recentPerformance}
        - {$c->previousCycle}

        Réponds avec UNIQUEMENT un objet JSON (aucun texte, aucune balise markdown) de cette forme EXACTE :
        {
          "name": "nom court du cycle (ex. C2 Développement)",
          "focus": "1 phrase sur l'objectif du cycle",
          "sessions": [
            {
              "dayOffset": 0,               // entier, 0 = jour de début, jusqu'à {$days}-1
              "type": "EASY|LONG|THRESHOLD|INTERVALS|RECOVERY|RACE_PACE|CROSS|REST",
              "title": "titre court",
              "description": "consigne précise (durée, allure, structure)",
              "targetDistanceMeters": int|null,
              "targetDurationSeconds": int|null,
              "targetPaceSecondsPerKm": int|null
            }
          ]
        }

        Règles : une entrée par jour pour les {$days} jours (inclure les jours de repos avec type REST). Progression cohérente, principe hard/easy, majorité en EASY, adaptation au ressenti et au cycle précédent. Allures en secondes par km (ex. 4:00/km = 240).
        PROMPT;
    }
}
