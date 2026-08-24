<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Thin streaming client for the Google Gemini API (free tier). Consumes the SSE
 * stream, forwards text deltas, and captures a single function call if any.
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
        if (trim($this->apiKey) === '') {
            throw new RuntimeException("La clé API Gemini n'est pas configurée (GEMINI_API_KEY).");
        }

        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => ['maxOutputTokens' => 8192, 'temperature' => 0.7],
        ];
        if ($tools !== []) {
            $body['tools'] = $tools;
        }

        $url = self::BASE.$this->model.':streamGenerateContent?alt=sse';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->withOptions(['stream' => true])
                ->timeout(180)
                ->post($url, $body);
        } catch (Throwable $e) {
            throw new RuntimeException('Gemini est indisponible : '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $detail = $response->json('error.message');
            throw new RuntimeException('Gemini est indisponible (HTTP '.$response->status().')'.(is_string($detail) ? ' : '.$detail : '').'.');
        }

        return $this->consume($response->toPsrResponse()->getBody(), $onText);
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
