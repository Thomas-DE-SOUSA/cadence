<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Ai;

use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\Port\CoachChat;
use Cadence\Coaching\Domain\Port\CoachStreamer;
use Cadence\Coaching\Domain\ValueObject\CoachContext;
use Cadence\Coaching\Domain\ValueObject\CoachReply;
use Cadence\Coaching\Domain\ValueObject\SessionProposal;
use Cadence\Shared\Infrastructure\Ai\GeminiClient;

/**
 * Coach on Google Gemini (free tier), streaming and blocking. Reuses the shared
 * prompt builder; maps Anthropic-style history to Gemini `contents`, and the
 * session-change tool to a Gemini function declaration.
 */
final class GeminiCoachStreamer implements CoachStreamer, CoachChat
{
    public function __construct(
        private readonly GeminiClient $client,
        private readonly CoachRequestBuilder $builder,
    ) {
    }

    /** @param list<Message> $history */
    public function reply(CoachContext $context, array $history): CoachReply
    {
        return $this->stream($context, $history, static fn (string $delta): null => null);
    }

    public function stream(CoachContext $context, array $history, callable $onText): CoachReply
    {
        $contents = array_map(
            static fn (array $m): array => [
                'role' => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $m['content']]],
            ],
            $this->builder->messages($history),
        );

        $result = $this->client->stream($this->builder->system($context), $contents, [['functionDeclarations' => [self::proposeFunction()]]], $onText);

        $proposal = null;
        if ($result['functionCall'] !== null && $result['functionCall']['name'] === 'propose_session_change') {
            $proposal = self::toProposal($result['functionCall']['args']);
        }

        $text = $result['text'];
        if ($text === '' && $proposal !== null) {
            $text = $proposal->rationale;
        }

        return new CoachReply($text === '' ? "Je n'ai pas de réponse pour le moment." : $text, $proposal);
    }

    /** @param array<string, mixed> $input */
    private static function toProposal(array $input): SessionProposal
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

    /** @return array<string, mixed> */
    private static function proposeFunction(): array
    {
        return [
            'name' => 'propose_session_change',
            'description' => "Propose de remplacer la séance planifiée d'un jour. À utiliser UNIQUEMENT quand un changement est justifié, après l'avoir expliqué en texte.",
            'parameters' => [
                'type' => 'OBJECT',
                'properties' => [
                    'date' => ['type' => 'STRING', 'description' => 'Jour à modifier, format YYYY-MM-DD'],
                    'session_type' => ['type' => 'STRING', 'enum' => ['EASY', 'LONG', 'THRESHOLD', 'INTERVALS', 'RECOVERY', 'RACE_PACE', 'RACE', 'CROSS', 'REST']],
                    'title' => ['type' => 'STRING'],
                    'description' => ['type' => 'STRING', 'description' => 'Consigne précise (durée, allure, structure)'],
                    'target_distance_meters' => ['type' => 'INTEGER', 'nullable' => true],
                    'target_duration_seconds' => ['type' => 'INTEGER', 'nullable' => true],
                    'target_pace_seconds_per_km' => ['type' => 'INTEGER', 'nullable' => true],
                    'rationale' => ['type' => 'STRING', 'description' => 'Pourquoi ce changement, 1-2 phrases'],
                ],
                'required' => ['date', 'session_type', 'title', 'description', 'rationale'],
            ],
        ];
    }
}
