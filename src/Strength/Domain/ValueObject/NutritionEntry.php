<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\ValueObject;

use Cadence\Strength\Domain\Enum\Meal;
use InvalidArgumentException;

/**
 * One logged food item on a day: which meal, a free-text description, and its
 * (estimated) calories and macros in grams. Estimates come from the AI parser;
 * the domain only guards that the numbers are sane and non-negative.
 */
final readonly class NutritionEntry
{
    public function __construct(
        public string $id,
        public string $date,          // Y-m-d
        public Meal $meal,
        public string $description,
        public int $kcal,
        public int $proteinG,
        public int $fatG,
        public int $carbsG,
    ) {
        if (trim($description) === '') {
            throw new InvalidArgumentException('A nutrition entry needs a description.');
        }

        foreach (['kcal' => $kcal, 'proteinG' => $proteinG, 'fatG' => $fatG, 'carbsG' => $carbsG] as $label => $value) {
            if ($value < 0) {
                throw new InvalidArgumentException(sprintf('%s must be non-negative, got %d.', $label, $value));
            }
        }

        if ($kcal > 15000) {
            throw new InvalidArgumentException(sprintf('kcal looks wrong (%d).', $kcal));
        }
    }
}
