<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Ai;

use Cadence\Coaching\Domain\Port\CoachStreamer;
use Cadence\Coaching\Domain\ValueObject\CoachContext;
use Cadence\Coaching\Domain\ValueObject\CoachReply;
use Cadence\Coaching\Domain\ValueObject\SessionProposal;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Streaming AI coach: consumes Claude's SSE stream, forwards each text delta to
 * the caller as it arrives, and assembles the final reply (text + proposal).
 */
final class ClaudeCoachStreamer implements CoachStreamer
{
    private const MODEL = 'claude-opus-4-8';

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly string $apiKey,
        private readonly CoachRequestBuilder $builder,
    ) {
    }

    public function stream(CoachContext $context, array $history, callable $onText): CoachReply
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException('La clé API Anthropic n\'est pas configurée (ANTHROPIC_API_KEY).');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->withOptions(['stream' => true])->timeout(180)->post(self::ENDPOINT, [
                'model' => self::MODEL,
                'max_tokens' => 4096,
                'stream' => true,
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

        return $this->consume($response->toPsrResponse()->getBody(), $onText);
    }

    private function consume(\Psr\Http\Message\StreamInterface $body, callable $onText): CoachReply
    {
        $text = '';
        $proposal = null;
        $toolBlocks = []; // index => ['name' => ..., 'json' => ...]
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));
                if ($payload === '' || $payload === '[DONE]') {
                    continue;
                }

                $event = json_decode($payload, true);
                if (! is_array($event)) {
                    continue;
                }

                $type = $event['type'] ?? null;
                $index = is_int($event['index'] ?? null) ? $event['index'] : 0;

                if ($type === 'content_block_start') {
                    $block = $event['content_block'] ?? [];
                    if (is_array($block) && ($block['type'] ?? null) === 'tool_use') {
                        $toolBlocks[$index] = ['name' => (string) ($block['name'] ?? ''), 'json' => ''];
                    }
                } elseif ($type === 'content_block_delta') {
                    $delta = $event['delta'] ?? [];
                    if (! is_array($delta)) {
                        continue;
                    }
                    if (($delta['type'] ?? null) === 'text_delta' && is_string($delta['text'] ?? null)) {
                        $text .= $delta['text'];
                        $onText($delta['text']);
                    } elseif (($delta['type'] ?? null) === 'input_json_delta' && isset($toolBlocks[$index])) {
                        $toolBlocks[$index]['json'] .= (string) ($delta['partial_json'] ?? '');
                    }
                } elseif ($type === 'content_block_stop' && isset($toolBlocks[$index])) {
                    if ($toolBlocks[$index]['name'] === 'propose_session_change') {
                        $decoded = json_decode($toolBlocks[$index]['json'], true);
                        if (is_array($decoded)) {
                            $proposal = ClaudeCoachChat::toProposal($decoded);
                        }
                    }
                }
            }
        }

        $text = trim($text);
        if ($text === '' && $proposal instanceof SessionProposal) {
            $text = $proposal->rationale;
        }

        return new CoachReply($text === '' ? 'Je n\'ai pas de réponse pour le moment.' : $text, $proposal);
    }
}
