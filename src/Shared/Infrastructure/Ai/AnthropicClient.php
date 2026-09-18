<?php

declare(strict_types=1);

namespace Cadence\Shared\Infrastructure\Ai;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Minimal client for the Anthropic Messages API (raw HTTP, mirroring
 * {@see GeminiClient}). Exposes a blocking, JSON-schema-constrained completion
 * used by the food estimator — structured outputs guarantee valid JSON, so no
 * fence-stripping or lenient parsing is needed.
 */
final class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-haiku-4-5',
    ) {
    }

    /**
     * Blocking completion constrained to a JSON schema. Returns the response
     * text (valid JSON for the given schema).
     *
     * @param array<string, mixed> $schema
     */
    public function completeJson(string $system, string $user, array $schema, int $maxTokens = 1024): string
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException("La clé API Anthropic n'est pas configurée (ANTHROPIC_API_KEY).");
        }

        $response = $this->post([
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
        ]);

        $text = '';
        foreach ((array) $response->json('content') as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $text .= $block['text'];
            }
        }

        return trim($text);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(array $body): Response
    {
        // Retry transient overload/rate-limit under one time budget kept below
        // PHP's execution limit, so a spike degrades to a clean error, not a 500.
        $deadline = microtime(true) + 26.0;
        $delayMs = 500;
        $lastError = null;

        while (true) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 1.0) {
                break;
            }
            $timeout = (int) max(3, min(15, (int) floor($remaining)));

            try {
                $response = Http::withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => self::VERSION,
                    'content-type' => 'application/json',
                ])->timeout($timeout)->post(self::ENDPOINT, $body);
            } catch (Throwable $e) {
                $lastError = new RuntimeException('Anthropic est indisponible : '.$e->getMessage(), 0, $e);
                $response = null;
            }

            if ($response !== null) {
                if (! $response->failed()) {
                    return $response;
                }
                $status = $response->status();
                $detail = $response->json('error.message');
                $message = 'Anthropic est indisponible (HTTP '.$status.')'.(is_string($detail) ? ' : '.$detail : '').'.';

                // Retry transient overload/rate-limit; fail fast on client errors (4xx).
                if (! in_array($status, [408, 409, 429, 500, 502, 503, 504, 529], true)) {
                    throw new RuntimeException($message);
                }
                $lastError = new RuntimeException($message);
            }

            if ($deadline - microtime(true) <= $delayMs / 1000 + 3) {
                break;
            }
            usleep($delayMs * 1000);
            $delayMs = (int) min($delayMs * 2, 2000);
        }

        throw $lastError ?? new RuntimeException('Anthropic est indisponible.');
    }
}
