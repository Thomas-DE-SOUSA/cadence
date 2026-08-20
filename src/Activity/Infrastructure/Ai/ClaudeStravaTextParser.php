<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Ai;

use Cadence\Activity\Application\Port\Exception\ActivityTextUnparseable;
use Cadence\Activity\Application\Port\ParsedActivity;
use Cadence\Activity\Application\Port\StravaTextParser;
use Cadence\Activity\Application\UseCase\RecordActivity\BestEffortInput;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Parses pasted activity text with Claude (Messages API). The prompt forces a
 * strict JSON object, which we decode and validate — no free-text guessing.
 */
final class ClaudeStravaTextParser implements StravaTextParser
{
    private const MODEL = 'claude-opus-4-8';

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function __construct(private readonly string $apiKey)
    {
    }

    public function parse(string $rawText): ParsedActivity
    {
        if (trim($this->apiKey) === '') {
            throw new ActivityTextUnparseable('The Anthropic API key is not configured (set ANTHROPIC_API_KEY).');
        }

        if (trim($rawText) === '') {
            throw new ActivityTextUnparseable('No text to parse.');
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(60)->post(self::ENDPOINT, [
                'model' => self::MODEL,
                'max_tokens' => 4096,
                'messages' => [['role' => 'user', 'content' => $this->prompt($rawText)]],
            ]);
        } catch (Throwable $e) {
            throw new ActivityTextUnparseable('The AI request failed: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new ActivityTextUnparseable('The AI request failed (HTTP '.$response->status().').');
        }

        $text = $response->json('content.0.text');
        if (! is_string($text)) {
            throw new ActivityTextUnparseable('Unexpected AI response shape.');
        }

        return $this->toParsedActivity($this->decodeJson($text));
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $text): array
    {
        $clean = trim($text);
        // Strip markdown code fences if the model wrapped the JSON.
        $clean = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $clean);
        $decoded = json_decode(trim($clean), true);

        if (! is_array($decoded)) {
            throw new ActivityTextUnparseable('The AI did not return valid JSON.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $d */
    private function toParsedActivity(array $d): ParsedActivity
    {
        $splits = [];
        foreach (is_array($d['splits'] ?? null) ? $d['splits'] : [] as $s) {
            if (is_array($s)) {
                $splits[] = new SplitInput(
                    (int) ($s['index'] ?? 0),
                    (int) ($s['distanceMeters'] ?? 0),
                    (int) ($s['durationSeconds'] ?? 0),
                    (int) ($s['elevationMeters'] ?? 0),
                );
            }
        }

        $efforts = [];
        foreach (is_array($d['bestEfforts'] ?? null) ? $d['bestEfforts'] : [] as $b) {
            if (is_array($b)) {
                $efforts[] = new BestEffortInput(
                    (string) ($b['label'] ?? ''),
                    (int) ($b['distanceMeters'] ?? 0),
                    (int) ($b['durationSeconds'] ?? 0),
                    (bool) ($b['isPersonalRecord'] ?? false),
                );
            }
        }

        $moving = (int) ($d['movingSeconds'] ?? 0);
        $elapsed = (int) ($d['elapsedSeconds'] ?? 0);

        return new ParsedActivity(
            occurredAtIso: (string) ($d['occurredAtIso'] ?? ''),
            distanceMeters: (int) ($d['distanceMeters'] ?? 0),
            movingSeconds: $moving,
            elapsedSeconds: $elapsed > 0 ? $elapsed : $moving,
            elevationGainMeters: (int) ($d['elevationGainMeters'] ?? 0),
            splits: array_values($splits),
            bestEfforts: array_values($efforts),
            externalId: (string) ($d['externalId'] ?? ''),
        );
    }

    private function prompt(string $rawText): string
    {
        return <<<PROMPT
        You extract structured running-activity data from pasted text (usually a Strava summary, French or English).

        Respond with ONLY a JSON object — no markdown fences, no prose — with EXACTLY this shape:
        {
          "occurredAtIso": "ISO-8601 date, e.g. 2026-08-19T18:00:00+00:00 (use midday UTC if only a date; today if none)",
          "distanceMeters": int,          // "10,01 km" -> 10010
          "movingSeconds": int,           // "42:35" -> 2555
          "elapsedSeconds": int,          // equal to movingSeconds if absent
          "elevationGainMeters": int,     // 0 if unknown
          "splits": [ { "index": int, "distanceMeters": int, "durationSeconds": int, "elevationMeters": int } ],
          "bestEfforts": [ { "label": string, "distanceMeters": int, "durationSeconds": int, "isPersonalRecord": bool } ],
          "externalId": "provider/activity id if present, else empty string"
        }

        Rules: index starts at 1; a split time like "4:15" is 255 durationSeconds; elevationMeters is the signed delta.
        Only include splits and best efforts that actually appear in the text; never invent data.

        TEXT:
        {$rawText}
        PROMPT;
    }
}
