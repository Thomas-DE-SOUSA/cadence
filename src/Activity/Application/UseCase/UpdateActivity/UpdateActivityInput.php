<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\UpdateActivity;

use Cadence\Activity\Application\UseCase\RecordActivity\BestEffortInput;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;

final readonly class UpdateActivityInput
{
    /**
     * @param list<SplitInput> $splits
     * @param list<BestEffortInput> $bestEfforts
     */
    public function __construct(
        public string $activityId,
        public string $occurredAt,
        public int $distanceMeters,
        public int $movingSeconds,
        public int $elapsedSeconds,
        public int $elevationGainMeters,
        public array $splits = [],
        public array $bestEfforts = [],
    ) {
    }
}
