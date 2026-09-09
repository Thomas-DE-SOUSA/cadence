<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Read;

/**
 * The nutrition reference shown in the muscu world. Fixed lean-bulk day
 * (~3600 kcal) split across three meals (no snack): protein ~2 g/kg spread
 * evenly, fat ~1.1 g/kg, carbs fill the rest and lean toward training. Pure,
 * deterministic — no persistence yet (targets can become editable later).
 */
final class NutritionView
{
    /**
     * @return array{daily: array<string,int>, meals: list<array{key:string,label:string,share:int,kcal:int,protein:int,fat:int,carbs:int,note:string}>}
     */
    public static function leanBulkPlan(): array
    {
        $meals = [
            [
                'key' => 'matin', 'label' => 'Matin', 'share' => 20,
                'kcal' => 720, 'protein' => 40, 'fat' => 22, 'carbs' => 90,
                'note' => "Petit-déj léger : skyr + flocons d'avoine + 2 œufs + un peu de bacon (~555 kcal), ajoute une banane pour compléter à ~720.",
            ],
            [
                'key' => 'midi', 'label' => 'Midi', 'share' => 40,
                'kcal' => 1440, 'protein' => 60, 'fat' => 40, 'carbs' => 210,
                'note' => "Gros repas. Protéine (viande/poisson) + féculent généreux (riz, pâtes, patate) + légumes + huile d'olive. Cale-le idéalement autour de ton entraînement.",
            ],
            [
                'key' => 'soir', 'label' => 'Soir', 'share' => 40,
                'kcal' => 1440, 'protein' => 60, 'fat' => 33, 'carbs' => 225,
                'note' => "Aussi copieux. Protéine + féculent + légumes. Si tu fais pompes/abdos le soir, garde des glucides ici pour la récup.",
            ],
        ];

        return [
            'daily' => ['kcal' => 3600, 'protein' => 160, 'fat' => 95, 'carbs' => 525],
            'meals' => $meals,
        ];
    }
}
