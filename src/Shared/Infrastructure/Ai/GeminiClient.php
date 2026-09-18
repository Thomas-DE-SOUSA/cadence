<?php

declare(strict_types=1);

namespace Cadence\Shared\Infrastructure\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Shared client for the Google Gemini API (free tier). Streaming for the coach /
 * advisory, and a blocking `complete()` (optionally JSON-constrained) for the
 * cycle planner and the Strava-text parser.
 */
final class GeminiClient
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * @param list<string> $fallbackModels tried, in order, if the primary model is unavailable (e.g. HTTP 503) on blocking calls
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-flash-latest',
        private readonly array $fallbackModels = [],
    ) {
    }

    /**
     * @param list<array{role:string,parts:list<array{text:string}>}> $contents
     * @param list<array<string,mixed>> $tools
     * @param callable(string):void $onText
     *
     * @return array{text:string,functionCall:array{name:string,args:array<string,mixed>}|null}
     */
    public function stream(string $system, array $contents, array $tools, callable $onText): array
    {
        $this->guardKey();

        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => 8192, 'temperature' => 0.7],
        ];
        if ($tools !== []) {
            $body['tools'] = $tools;
        }

        $response = $this->post($this->model.':streamGenerateContent?alt=sse', $body, true);

        return $this->consume($response->toPsrResponse()->getBody(), $onText);
    }

    /**
     * Blocking completion; returns the concatenated text of the first candidate.
     *
     * @param array<string,mixed> $generationConfig extra generationConfig (e.g. responseMimeType/maxOutputTokens)
     */
    public function complete(string $system, string $user, array $generationConfig = []): string
    {
        $this->guardKey();

        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => array_merge(['maxOutputTokens' => 8192, 'temperature' => 0.4], $generationConfig),
        ];

        // One shared time budget across every retry AND fallback model, kept
        // safely under PHP's 30s max_execution_time so a Gemini spike can never
        // turn into a 500 (it degrades to a clean "unavailable" error instead).
        $deadline = microtime(true) + 26.0;
        $lastError = null;
        foreach ([$this->model, ...$this->fallbackModels] as $model) {
            if (microtime(true) >= $deadline) {
                break;
            }
            try {
                $response = $this->post($model.':generateContent', $body, false, $deadline);
            } catch (RuntimeException $e) {
                $lastError = $e;

                continue; // model unavailable (e.g. 503) → try the next one
            }

            $text = '';
            foreach ((array) $response->json('candidates.0.content.parts') as $part) {
                if (is_array($part) && is_string($part['text'] ?? null)) {
                    $text .= $part['text'];
                }
            }

            return trim($text);
        }

        throw $lastError ?? new RuntimeException('Gemini est indisponible.');
    }

    /** Blocking multimodal call: a text prompt + one inline image, returns text (JSON mode friendly). */
    public function completeVision(string $system, string $user, string $imageBase64, string $mimeType, array $generationConfig = []): string
    {
        $this->guardKey();

        $response = $this->post($this->model.':generateContent', [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [
                ['text' => $user],
                ['inlineData' => ['mimeType' => $mimeType, 'data' => $imageBase64]],
            ]]],
            'generationConfig' => array_merge(['maxOutputTokens' => 4096, 'temperature' => 0.2], $generationConfig),
        ], false, microtime(true) + 26.0);

        $text = '';
        foreach ((array) $response->json('candidates.0.content.parts') as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return trim($text);
    }

    private function guardKey(): void
    {
        if (trim($this->apiKey) === '') {
            throw new RuntimeException("La clé API Gemini n'est pas configurée (GEMINI_API_KEY).");
        }
    }

    /**
     * @param array<string,mixed> $body
     */
    private function post(string $path, array $body, bool $stream, ?float $deadline = null): \Illuminate\Http\Client\Response
    {
        // Streaming: a single long-lived attempt (the coach reads incrementally).
        if ($stream) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->timeout(180)->withOptions(['stream' => true])->post(self::BASE.$path, $body);
            } catch (Throwable $e) {
                throw new RuntimeException('Gemini est indisponible : '.$e->getMessage(), 0, $e);
            }
            if ($response->failed()) {
                $detail = $response->json('error.message');
                throw new RuntimeException('Gemini est indisponible (HTTP '.$response->status().')'.(is_string($detail) ? ' : '.$detail : '').'.');
            }

            return $response;
        }

        // Blocking: retry transient overload (Gemini free-tier "high demand"
        // 503/429 spikes) with backoff, but each attempt's timeout is capped by
        // the time left before the shared deadline — so the whole thing stays
        // under PHP's execution limit and never becomes a 500.
        $deadline ??= microtime(true) + 26.0;
        $delayMs = 500;
        $lastError = null;

        while (true) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 1.0) {
                break;
            }
            $timeout = (int) max(3, min(12, (int) floor($remaining)));

            try {
                $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->timeout($timeout)->post(self::BASE.$path, $body);
            } catch (Throwable $e) {
                $lastError = new RuntimeException('Gemini est indisponible : '.$e->getMessage(), 0, $e);
                $response = null;
            }

            if ($response !== null) {
                if (! $response->failed()) {
                    return $response;
                }
                $status = $response->status();
                $detail = $response->json('error.message');
                $message = 'Gemini est indisponible (HTTP '.$status.')'.(is_string($detail) ? ' : '.$detail : '').'.';
                // Non-transient (4xx other than 429) → give up on this model now.
                if (! in_array($status, [429, 500, 502, 503, 504], true)) {
                    throw new RuntimeException($message);
                }
                $lastError = new RuntimeException($message);
            }

            // Back off only if there's budget left for another attempt.
            if ($deadline - microtime(true) <= $delayMs / 1000 + 3) {
                break;
            }
            usleep($delayMs * 1000);
            $delayMs = (int) min($delayMs * 2, 2000);
        }

        throw $lastError ?? new RuntimeException('Gemini est indisponible.');
    }

    /**
     * @param callable(string):void $onText
     *
     * @return array{text:string,functionCall:array{name:string,args:array<string,mixed>}|null}
     */
    private function consume(\Psr\Http\Message\StreamInterface $body, callable $onText): array
    {
        $text = '';
        $functionCall = null;
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
                if ($payload === '') {
                    continue;
                }

                $event = json_decode($payload, true);
                if (! is_array($event)) {
                    continue;
                }

                foreach ($event['candidates'] ?? [] as $candidate) {
                    foreach ($candidate['content']['parts'] ?? [] as $part) {
                        if (is_string($part['text'] ?? null)) {
                            $text .= $part['text'];
                            $onText($part['text']);
                        } elseif (isset($part['functionCall']) && is_array($part['functionCall'])) {
                            $fc = $part['functionCall'];
                            $functionCall = [
                                'name' => (string) ($fc['name'] ?? ''),
                                'args' => is_array($fc['args'] ?? null) ? $fc['args'] : [],
                            ];
                        }
                    }
                }
            }
        }

        return ['text' => trim($text), 'functionCall' => $functionCall];
    }
}
