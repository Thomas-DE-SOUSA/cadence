<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\ValueObject;

use Cadence\Strength\Domain\Enum\WeighMoment;
use InvalidArgumentException;

/**
 * One body-weight reading: a day, whether it was taken in the morning or the
 * evening, and the weight in kg. Two readings a day, averaged over a Mon–Sun
 * week, smooth out daily water/food noise so week-to-week trends are real.
 */
final readonly class WeightEntry
{
    public function __construct(
        public string $date,          // Y-m-d
        public WeighMoment $moment,
        public float $weightKg,
        public string $note = '',
    ) {
        if ($weightKg <= 0) {
            throw new InvalidArgumentException(sprintf('weightKg must be positive, got %g.', $weightKg));
        }

        if ($weightKg > 500) {
            throw new InvalidArgumentException(sprintf('weightKg looks wrong (%g kg).', $weightKg));
        }
    }
}
