<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Streams the guest-advisory diagnostic from Claude over SSE, forwarding each
 * text delta to the caller. Free-form Markdown reply, no tools.
 */
final class AdvisorStreamer
{
    private const MODEL = 'claude-opus-4-8';

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function __construct(private readonly string $apiKey)
    {
    }

    /** @param callable(string):void $onText */
    public function stream(string $system, string $user, callable $onText): string
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException("La clé API Anthropic n'est pas configurée (ANTHROPIC_API_KEY).");
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
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Le conseiller est indisponible : '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Le conseiller est indisponible (HTTP '.$response->status().').');
        }

        return $this->consume($response->toPsrResponse()->getBody(), $onText);
    }

    /** @param callable(string):void $onText */
    private function consume(\Psr\Http\Message\StreamInterface $body, callable $onText): string
    {
        $text = '';
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
                if (! is_array($event) || ($event['type'] ?? null) !== 'content_block_delta') {
                    continue;
                }
                $delta = $event['delta'] ?? [];
                if (is_array($delta) && ($delta['type'] ?? null) === 'text_delta' && is_string($delta['text'] ?? null)) {
                    $text .= $delta['text'];
                    $onText($delta['text']);
                }
            }
        }

        return trim($text);
    }
}
