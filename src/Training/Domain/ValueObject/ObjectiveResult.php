<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\ValueObject;

/** The evaluation of one objective against a set of assigned activities. */
final class ObjectiveResult
{
    public function __construct(
        public readonly string $objectiveId,
        public readonly string $label,
        public readonly string $type,
        public readonly bool $achieved,
        /** 0.0 … 1.0 */
        public readonly float $progress,
        public readonly string $detail,
        public readonly ?string $achievedByActivityId = null,
    ) {
    }
}
