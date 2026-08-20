<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\Port;

use Cadence\Activity\Application\UseCase\RecordActivity\BestEffortInput;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;

/**
 * Provider-agnostic result of parsing pasted activity text. Whatever parses the
 * text (an LLM adapter, a deterministic parser) returns this shape.
 */
final readonly class ParsedActivity
{
    /**
     * @param list<SplitInput> $splits
     * @param list<BestEffortInput> $bestEfforts
     */
    public function __construct(
        public string $occurredAtIso,
        public int $distanceMeters,
        public int $movingSeconds,
        public int $elapsedSeconds,
        public int $elevationGainMeters,
        public array $splits,
        public array $bestEfforts,
        public string $externalId = '',
    ) {
    }
}
