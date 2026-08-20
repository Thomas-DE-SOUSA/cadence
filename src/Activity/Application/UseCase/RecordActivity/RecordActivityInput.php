<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\RecordActivity;

final readonly class RecordActivityInput
{
    /**
     * @param list<SplitInput> $splits
     * @param list<BestEffortInput> $bestEfforts
     */
    public function __construct(
        public string $tenantId,
        public string $occurredAt,
        public string $source,
        public int $distanceMeters,
        public int $movingSeconds,
        public int $elapsedSeconds,
        public int $elevationGainMeters,
        public array $splits = [],
        public array $bestEfforts = [],
    ) {
    }
}
