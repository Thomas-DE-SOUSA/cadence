<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\ValueObject;

use Cadence\Strength\Domain\Enum\ProgressionKind;

/**
 * One exercise that beat its previous session: what improved (weight or reps),
 * and the before/after figures on its top working set. Snapshotted name so the
 * message stays stable even if the catalog entry is later renamed.
 */
final readonly class ExerciseProgression
{
    public function __construct(
        public string $exerciseId,
        public string $name,
        public ProgressionKind $kind,
        public ?float $fromWeightKg,
        public ?float $toWeightKg,
        public ?int $fromReps,
        public ?int $toReps,
    ) {
    }

    /** Load added on the top working set (kg); 0 for a reps-only progression. */
    public function weightDeltaKg(): float
    {
        return ($this->toWeightKg ?? 0.0) - ($this->fromWeightKg ?? 0.0);
    }

    /** Reps added on the top working set; 0 for a weight-only progression. */
    public function repsDelta(): int
    {
        return ($this->toReps ?? 0) - ($this->fromReps ?? 0);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'exerciseId' => $this->exerciseId,
            'name' => $this->name,
            'kind' => $this->kind->value,
            'fromWeightKg' => $this->fromWeightKg,
            'toWeightKg' => $this->toWeightKg,
            'weightDeltaKg' => round($this->weightDeltaKg(), 2),
            'fromReps' => $this->fromReps,
            'toReps' => $this->toReps,
            'repsDelta' => $this->repsDelta(),
        ];
    }
}
