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

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-3.6-flash',
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

        $response = $this->post($this->model.':generateContent', [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'generationConfig' => array_merge(['maxOutputTokens' => 8192, 'temperature' => 0.4], $generationConfig),
        ], false);

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
    private function post(string $path, array $body, bool $stream): \Illuminate\Http\Client\Response
    {
        try {
            $request = Http::withHeaders(['x-goog-api-key' => $this->apiKey])->timeout(180);
            if ($stream) {
                $request = $request->withOptions(['stream' => true]);
            }
            $response = $request->post(self::BASE.$path, $body);
        } catch (Throwable $e) {
            throw new RuntimeException('Gemini est indisponible : '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $detail = $response->json('error.message');
            throw new RuntimeException('Gemini est indisponible (HTTP '.$response->status().')'.(is_string($detail) ? ' : '.$detail : '').'.');
        }

        return $response;
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
