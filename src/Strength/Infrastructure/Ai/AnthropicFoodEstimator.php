<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Ai;

use Cadence\Shared\Infrastructure\Ai\AnthropicClient;
use Cadence\Strength\Application\Port\EstimatedFood;
use Cadence\Strength\Application\Port\Exception\FoodEstimationFailed;
use Cadence\Strength\Application\Port\FoodEstimator;
use Throwable;

/**
 * Estimates calories and macros from a free-text meal description with Claude
 * (Haiku by default). Structured outputs constrain the reply to a strict JSON
 * schema, so the result is always a well-formed object — no prose, no fences.
 */
final class AnthropicFoodEstimator implements FoodEstimator
{
    public function __construct(private readonly AnthropicClient $client)
    {
    }

    public function estimate(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        try {
            $raw = $this->client->completeJson(
                'You are a nutrition estimator. You estimate calories and macros of foods described in French (brands and quantities included).',
                $this->prompt($text),
                $this->schema(),
            );
        } catch (Throwable $e) {
            throw new FoodEstimationFailed('The AI estimation failed: '.$e->getMessage(), 0, $e);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new FoodEstimationFailed('The AI did not return valid JSON.');
        }

        return $this->toFoods($decoded);
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        $item = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'kcal' => ['type' => 'integer'],
                'protein_g' => ['type' => 'integer'],
                'fat_g' => ['type' => 'integer'],
                'carbs_g' => ['type' => 'integer'],
            ],
            'required' => ['name', 'kcal', 'protein_g', 'fat_g', 'carbs_g'],
            'additionalProperties' => false,
        ];

        return [
            'type' => 'object',
            'properties' => ['items' => ['type' => 'array', 'items' => $item]],
            'required' => ['items'],
            'additionalProperties' => false,
        ];
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

        Rules:
        - One entry per distinct food/drink. Fold a stated count into ONE entry (e.g. "25 bâtons du berger" → one item named "25 bâtons du berger" with the TOTAL for 25).
        - Interpret portions realistically: "énorme part" = large portion, "un verre", "une canette 500 ml", etc.
        - Know common brands (e.g. Monster Bad Apple = sugared energy drink ~230 kcal / 500 ml; Le Bâton de Berger mini ≈ 9 g, ~40 kcal each).
        - "name" is a short French label including the quantity. Round every number to an integer. Give your best realistic estimate.
        - If nothing edible is described, return an empty items array.

        TEXT:
        {$text}
        PROMPT;
    }
}
