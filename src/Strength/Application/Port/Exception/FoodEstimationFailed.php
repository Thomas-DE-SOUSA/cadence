<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\Port\Exception;

use RuntimeException;

/** The AI food-estimation service failed (network, quota, bad response…). */
final class FoodEstimationFailed extends RuntimeException
{
}
