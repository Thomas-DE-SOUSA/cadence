<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Ai;

use Cadence\Activity\Application\Port\ActivityPhotoParser;
use Cadence\Activity\Application\Port\Exception\ActivityTextUnparseable;
use Cadence\Activity\Application\Port\ParsedActivity;
use Cadence\Shared\Infrastructure\Ai\GeminiClient;
use Throwable;

/**
 * Reads a run off a photo of a watch / treadmill / app screen with Gemini
 * vision. Returns totals only (a photo rarely gives reliable per-km splits).
 */
final class GeminiActivityPhotoParser implements ActivityPhotoParser
{
    public function __construct(private readonly GeminiClient $client)
    {
    }

    public function parse(string $imageBytes, string $mimeType): ParsedActivity
    {
        if ($imageBytes === '') {
            throw new ActivityTextUnparseable('No image to read.');
        }

        try {
            $text = $this->client->completeVision(
                'You read running-activity data off a photo and output strict JSON.',
                $this->prompt(),
                base64_encode($imageBytes),
                $mimeType,
                ['responseMimeType' => 'application/json'],
            );
        } catch (Throwable $e) {
            throw new ActivityTextUnparseable('The AI request failed: '.$e->getMessage(), 0, $e);
        }

        $d = $this->decodeJson($text);
        $moving = (int) ($d['movingSeconds'] ?? 0);
        $elapsed = (int) ($d['elapsedSeconds'] ?? 0);

        if ((int) ($d['distanceMeters'] ?? 0) <= 0 || $moving <= 0) {
            throw new ActivityTextUnparseable('Je n’ai pas réussi à lire la distance et le temps sur la photo.');
        }

        return new ParsedActivity(
            occurredAtIso: (string) ($d['occurredAtIso'] ?? ''),
            distanceMeters: (int) $d['distanceMeters'],
            movingSeconds: $moving,
            elapsedSeconds: $elapsed > 0 ? $elapsed : $moving,
            elevationGainMeters: (int) ($d['elevationGainMeters'] ?? 0),
            splits: [],
            bestEfforts: [],
        );
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $text): array
    {
        $clean = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
        $decoded = json_decode(trim($clean), true);

        if (! is_array($decoded)) {
            throw new ActivityTextUnparseable('The AI did not return valid JSON.');
        }

        return $decoded;
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
        You are given a photo or screenshot of a running summary (a GPS watch face, a treadmill console, or a running app like Strava/Nike/Garmin — French or English).

        Extract the run and respond with ONLY a JSON object — no markdown fences, no prose — with EXACTLY this shape:
        {
          "occurredAtIso": "ISO-8601 date, e.g. 2026-08-19T18:00:00+00:00. Use the date shown; if only a time or nothing is shown, use today at midday UTC.",
          "distanceMeters": int,
          "movingSeconds": int,
          "elapsedSeconds": int,
          "elevationGainMeters": int
        }

        Rules:
        - Convert distance to meters (e.g. "8,42 km" -> 8420).
        - Convert any time to seconds (e.g. "48:12" -> 2892, "1:02:30" -> 3750).
        - If only a total/elapsed time is shown, use it for both movingSeconds and elapsedSeconds.
        - If elevation is not shown, use 0.
        - Never invent values you cannot read. If distance or time is unreadable, set them to 0.
        PROMPT;
    }
}
