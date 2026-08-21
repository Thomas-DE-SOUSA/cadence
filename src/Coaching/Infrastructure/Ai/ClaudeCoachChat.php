<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Ai;

use Cadence\Coaching\Domain\Enum\MessageRole;
use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\Port\CoachChat;
use Cadence\Coaching\Domain\ValueObject\CoachContext;
use Cadence\Coaching\Domain\ValueObject\CoachReply;
use Cadence\Coaching\Domain\ValueObject\FitnessSnapshot;
use Cadence\Coaching\Domain\ValueObject\PlannedDay;
use Cadence\Coaching\Domain\ValueObject\SessionProposal;
use Cadence\Coaching\Infrastructure\Knowledge\CoachingKnowledge;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * The AI coach: Claude Messages API, grounded in the doctrine + the athlete's
 * computed profile, able to search the web and to propose a structured day
 * change (a client tool we capture, never auto-apply).
 */
final class ClaudeCoachChat implements CoachChat
{
    private const MODEL = 'claude-opus-4-8';

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly string $apiKey,
        private readonly CoachingKnowledge $knowledge,
    ) {
    }

    public function reply(CoachContext $context, array $history): CoachReply
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('La clé API Anthropic n\'est pas configurée (ANTHROPIC_API_KEY).');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(180)->post(self::ENDPOINT, [
                'model' => self::MODEL,
                'max_tokens' => 4096,
                'thinking' => ['type' => 'adaptive'],
                'system' => $this->system($context),
                'messages' => $this->messages($history),
                'tools' => [
                    ['type' => 'web_search_20260209', 'name' => 'web_search', 'max_uses' => 3],
                    [
                        'name' => 'propose_session_change',
                        'description' => 'Propose de remplacer la séance planifiée d\'un jour. À utiliser UNIQUEMENT quand un changement est justifié, après avoir expliqué pourquoi en texte.',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'date' => ['type' => 'string', 'description' => 'Jour à modifier, format YYYY-MM-DD'],
                                'session_type' => ['type' => 'string', 'enum' => ['EASY', 'LONG', 'THRESHOLD', 'INTERVALS', 'RECOVERY', 'RACE_PACE', 'RACE', 'CROSS', 'REST']],
                                'title' => ['type' => 'string'],
                                'description' => ['type' => 'string', 'description' => 'Consigne précise (durée, allure, structure)'],
                                'target_distance_meters' => ['type' => ['integer', 'null']],
                                'target_duration_seconds' => ['type' => ['integer', 'null']],
                                'target_pace_seconds_per_km' => ['type' => ['integer', 'null']],
                                'rationale' => ['type' => 'string', 'description' => 'Pourquoi ce changement, 1-2 phrases'],
                            ],
                            'required' => ['date', 'session_type', 'title', 'description', 'rationale'],
                        ],
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Le coach est indisponible : '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Le coach est indisponible (HTTP '.$response->status().').');
        }

        return $this->parse($response->json('content'));
    }

    /** @param mixed $content */
    private function parse($content): CoachReply
    {
        $text = '';
        $proposal = null;

        foreach (is_array($content) ? $content : [] as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $text .= $block['text'];
            }
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === 'propose_session_change' && is_array($block['input'] ?? null)) {
                $proposal = $this->toProposal($block['input']);
            }
        }

        $text = trim($text);
        if ($text === '' && $proposal !== null) {
            $text = $proposal->rationale;
        }

        return new CoachReply($text === '' ? 'Je n\'ai pas de réponse pour le moment.' : $text, $proposal);
    }

    /** @param array<string, mixed> $input */
    private function toProposal(array $input): SessionProposal
    {
        return new SessionProposal(
            (string) ($input['date'] ?? ''),
            (string) ($input['session_type'] ?? 'EASY'),
            (string) ($input['title'] ?? ''),
            (string) ($input['description'] ?? ''),
            isset($input['target_distance_meters']) && $input['target_distance_meters'] !== null ? (int) $input['target_distance_meters'] : null,
            isset($input['target_duration_seconds']) && $input['target_duration_seconds'] !== null ? (int) $input['target_duration_seconds'] : null,
            isset($input['target_pace_seconds_per_km']) && $input['target_pace_seconds_per_km'] !== null ? (int) $input['target_pace_seconds_per_km'] : null,
            (string) ($input['rationale'] ?? ''),
        );
    }

    /**
     * @param list<Message> $history
     *
     * @return list<array{role:string,content:string}>
     */
    private function messages(array $history): array
    {
        $messages = [];
        foreach ($history as $message) {
            $content = $message->text;
            if ($message->role === MessageRole::COACH && $message->proposal !== null) {
                $content .= "\n[proposition envoyée : {$message->proposal->title}]";
            }
            $messages[] = [
                'role' => $message->role === MessageRole::ATHLETE ? 'user' : 'assistant',
                'content' => $content,
            ];
        }

        return $messages;
    }

    private function system(CoachContext $c): string
    {
        return implode("\n\n", [
            $this->knowledge->systemFoundation(),
            "# L'athlète et l'objectif\n".$this->athleteBlock($c),
            "# Le jour discuté\n".$this->dayBlock($c->day),
            $this->rules(),
        ]);
    }

    private function athleteBlock(CoachContext $c): string
    {
        $lines = [
            "- Objectif : {$c->goal}",
            '- Course cible : '.($c->targetRaceName !== '' ? $c->targetRaceName : 'non précisée').($c->targetRaceDate !== null ? " (le {$c->targetRaceDate})" : ''),
            "- Performances récentes : {$c->recentSummary}",
        ];

        if ($c->fitness instanceof FitnessSnapshot) {
            $p = $c->fitness->paces;
            $lines[] = sprintf('- VDOT estimé : %.1f (d\'après %.2f km).', $c->fitness->vdot, $c->fitness->referenceDistanceMeters / 1000);
            $lines[] = sprintf('- Allures perso (s/km) : E %d, M %d, T %d, I %d, R %d.', $p->easy, $p->marathon, $p->threshold, $p->interval, $p->repetition);
            if ($c->targetVdot !== null) {
                $lines[] = sprintf('- VDOT visé par l\'objectif : %.1f (écart %+.1f).', $c->targetVdot, $c->targetVdot - $c->fitness->vdot);
            }
        } else {
            $lines[] = '- Pas encore assez de données pour estimer le VDOT et les allures.';
        }

        return implode("\n", $lines);
    }

    private function dayBlock(PlannedDay $d): string
    {
        $lines = [
            "- Date : {$d->date}",
            "- Type : {$d->type}",
            "- Séance : {$d->title} — {$d->description}",
        ];
        if ($d->targetDistanceMeters !== null) {
            $lines[] = sprintf('- Distance cible : %.2f km', $d->targetDistanceMeters / 1000);
        }
        if ($d->targetPaceSecondsPerKm !== null) {
            $lines[] = "- Allure cible : {$d->targetPaceSecondsPerKm} s/km";
        }
        if ($d->actualSummary !== null) {
            $lines[] = "- Sortie déjà réalisée ce jour : {$d->actualSummary}";
        }

        return implode("\n", $lines);
    }

    private function rules(): string
    {
        return <<<RULES
        # Comment répondre
        - Réponds en français, comme le coach personnel de cet athlète. Concis, chaleureux, direct.
        - Appuie-toi sur SES allures et son VDOT ci-dessus ; n'invente jamais d'allures.
        - Explique brièvement le « pourquoi » (1-2 phrases) puis donne l'action concrète.
        - Utilise l'outil `propose_session_change` UNIQUEMENT si un changement de séance est justifié, et seulement APRÈS ton explication en texte. Sinon, réponds juste en texte.
        - Applique les règles de sécurité de la doctrine (drapeaux rouges → repos + avis médical).
        - Utilise `web_search` seulement pour du ponctuel-actuel (parcours d'une course, météo, étude récente), pas pour le socle que tu connais déjà.
        RULES;
    }
}
