<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Read;

use Cadence\Strength\Domain\ValueObject\NutritionEntry;

/**
 * The nutrition read model shown in the muscu world: fixed lean-bulk targets
 * (~3600 kcal, 20/40/40 across meals) and a day view that groups logged entries
 * by meal and sums them against those targets. Pure/deterministic.
 */
final class NutritionView
{
    /**
     * @return array{daily: array{kcal:int,protein:int,fat:int,carbs:int}, meals: list<array{key:string,label:string,share:int,kcal:int,protein:int,fat:int,carbs:int,note:string}>}
     */
    public static function leanBulkPlan(): array
    {
        return [
            'daily' => ['kcal' => 3600, 'protein' => 160, 'fat' => 95, 'carbs' => 525],
            'meals' => [
                ['key' => 'matin', 'label' => 'Matin', 'share' => 20, 'kcal' => 720, 'protein' => 40, 'fat' => 22, 'carbs' => 90,
                    'note' => "Petit-déj léger : skyr + flocons + 2 œufs + un peu de bacon, une banane pour compléter."],
                ['key' => 'midi', 'label' => 'Midi', 'share' => 40, 'kcal' => 1440, 'protein' => 60, 'fat' => 40, 'carbs' => 210,
                    'note' => "Gros repas. Protéine + féculent généreux + légumes + huile d'olive. Autour de l'entraînement."],
                ['key' => 'soir', 'label' => 'Soir', 'share' => 40, 'kcal' => 1440, 'protein' => 60, 'fat' => 33, 'carbs' => 225,
                    'note' => "Aussi copieux. Protéine + féculent + légumes. Glucides ici si pompes/abdos le soir."],
            ],
        ];
    }

    /**
     * Day view: the plan, plus each meal's logged entries and subtotals, plus
     * day totals and remaining vs. target.
     *
     * @param list<NutritionEntry> $entries
     *
     * @return array<string, mixed>
     */
    public static function day(string $date, array $entries): array
    {
        $plan = self::leanBulkPlan();

        $totals = ['kcal' => 0, 'protein' => 0, 'fat' => 0, 'carbs' => 0];
        $meals = [];
        foreach ($plan['meals'] as $m) {
            $rows = [];
            $sub = ['kcal' => 0, 'protein' => 0, 'fat' => 0, 'carbs' => 0];
            foreach ($entries as $e) {
                if ($e->meal->value !== $m['key']) {
                    continue;
                }
                $rows[] = [
                    'id' => $e->id,
                    'description' => $e->description,
                    'kcal' => $e->kcal,
                    'protein' => $e->proteinG,
                    'fat' => $e->fatG,
                    'carbs' => $e->carbsG,
                ];
                $sub['kcal'] += $e->kcal;
                $sub['protein'] += $e->proteinG;
                $sub['fat'] += $e->fatG;
                $sub['carbs'] += $e->carbsG;
            }
            $totals['kcal'] += $sub['kcal'];
            $totals['protein'] += $sub['protein'];
            $totals['fat'] += $sub['fat'];
            $totals['carbs'] += $sub['carbs'];

            $m['entries'] = $rows;
            $m['subtotal'] = $sub;
            $meals[] = $m;
        }

        $remaining = [
            'kcal' => $plan['daily']['kcal'] - $totals['kcal'],
            'protein' => $plan['daily']['protein'] - $totals['protein'],
            'fat' => $plan['daily']['fat'] - $totals['fat'],
            'carbs' => $plan['daily']['carbs'] - $totals['carbs'],
        ];

        return [
            'date' => $date,
            'daily' => $plan['daily'],
            'meals' => $meals,
            'totals' => $totals,
            'remaining' => $remaining,
        ];
    }
}
