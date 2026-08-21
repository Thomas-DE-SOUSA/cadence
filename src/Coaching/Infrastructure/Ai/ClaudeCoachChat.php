<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Ai;

use Cadence\Coaching\Domain\Port\CoachChat;
use Cadence\Coaching\Domain\ValueObject\CoachContext;
use Cadence\Coaching\Domain\ValueObject\CoachReply;
use Cadence\Coaching\Domain\ValueObject\SessionProposal;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * The AI coach (blocking): Claude Messages API, grounded in the doctrine + the
 * athlete's computed profile, able to search the web and propose a structured
 * day change (captured, never auto-applied).
 */
final class ClaudeCoachChat implements CoachChat
{
    private const MODEL = 'claude-opus-4-8';

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly string $apiKey,
        private readonly CoachRequestBuilder $builder,
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
                'system' => $this->builder->system($context),
                'messages' => $this->builder->messages($history),
                'tools' => $this->builder->tools(),
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
                $proposal = self::toProposal($block['input']);
            }
        }

        $text = trim($text);
        if ($text === '' && $proposal !== null) {
            $text = $proposal->rationale;
        }

        return new CoachReply($text === '' ? 'Je n\'ai pas de réponse pour le moment.' : $text, $proposal);
    }

    /** @param array<string, mixed> $input */
    public static function toProposal(array $input): SessionProposal
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
}
