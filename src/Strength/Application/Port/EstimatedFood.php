<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\Port;

/** One food item estimated from free text by the {@see FoodEstimator}. */
final readonly class EstimatedFood
{
    public function __construct(
        public string $name,
        public int $kcal,
        public int $proteinG,
        public int $fatG,
        public int $carbsG,
    ) {
    }
}
