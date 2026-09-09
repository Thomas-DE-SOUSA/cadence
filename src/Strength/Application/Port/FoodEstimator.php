<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\Port;

use Cadence\Strength\Application\Port\Exception\FoodEstimationFailed;

/** Turns a free-text meal description into estimated food items (via AI). */
interface FoodEstimator
{
    /**
     * @return list<EstimatedFood> may be empty if nothing could be recognised
     *
     * @throws FoodEstimationFailed when the estimation service fails
     */
    public function estimate(string $text): array;
}
