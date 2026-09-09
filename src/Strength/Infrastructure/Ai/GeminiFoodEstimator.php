<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Ai;

use Cadence\Shared\Infrastructure\Ai\GeminiClient;
use Cadence\Strength\Application\Port\EstimatedFood;
use Cadence\Strength\Application\Port\Exception\FoodEstimationFailed;
use Cadence\Strength\Application\Port\FoodEstimator;
use Throwable;

/**
 * Estimates calories and macros from a free-text meal description with Gemini.
 * JSON output mode forces a strict object we decode and validate. Estimates are
 * approximate by nature — the prompt asks for realistic best guesses, never prose.
 */
final class GeminiFoodEstimator implements FoodEstimator
{
    public function __construct(private readonly GeminiClient $client)
    {
    }

    public function estimate(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        try {
            $raw = $this->client->complete(
                'You are a nutrition estimator. You estimate calories and macros of foods described in French (brands and quantities included). Return strict JSON only.',
                $this->prompt($text),
                ['responseMimeType' => 'application/json', 'temperature' => 0.2],
            );
        } catch (Throwable $e) {
            throw new FoodEstimationFailed('The AI estimation failed: '.$e->getMessage(), 0, $e);
        }

        return $this->toFoods($this->decodeJson($raw));
    }

    /** @return array<string, mixed> */
    private function decodeJson(string $text): array
    {
        $clean = (string) preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
        $decoded = json_decode(trim($clean), true);

        if (! is_array($decoded)) {
            throw new FoodEstimationFailed('The AI did not return valid JSON.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $d
     *
     * @return list<EstimatedFood>
     */
    private function toFoods(array $d): array
    {
        $items = is_array($d['items'] ?? null) ? $d['items'] : [];
        $foods = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $foods[] = new EstimatedFood(
                mb_substr($name, 0, 255),
                max(0, (int) round((float) ($item['kcal'] ?? 0))),
                max(0, (int) round((float) ($item['protein_g'] ?? 0))),
                max(0, (int) round((float) ($item['fat_g'] ?? 0))),
                max(0, (int) round((float) ($item['carbs_g'] ?? 0))),
            );
        }

        return $foods;
    }

    private function prompt(string $text): string
    {
        return <<<PROMPT
        Estimate the calories and macronutrients of everything described below. The text is casual French and may include brands, drinks and loose quantities.

        Respond with ONLY a JSON object — no markdown fences, no prose — with EXACTLY this shape:
        {
          "items": [
            { "name": "short French label incl. quantity", "kcal": int, "protein_g": int, "fat_g": int, "carbs_g": int }
          ]
        }

        Rules:
        - One entry per distinct food/drink. Fold a stated count into ONE entry (e.g. "25 bâtons du berger" → one item named "25 bâtons du berger" with the TOTAL for 25).
        - Interpret portions realistically: "énorme part" = large portion, "un verre", "une canette 500 ml", etc.
        - Know common brands (e.g. Monster Bad Apple = sugared energy drink ~230 kcal / 500 ml; Le Bâton de Berger mini ≈ 9 g, ~40 kcal each).
        - Round every number to an integer. Give your best realistic estimate; never return prose or an empty name. If nothing edible is described, return {"items": []}.

        TEXT:
        {$text}
        PROMPT;
    }
}
