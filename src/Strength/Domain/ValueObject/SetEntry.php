<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\ValueObject;

use InvalidArgumentException;

/**
 * One logged set. Deliberately flexible to cover every case: loaded reps
 * (weight × reps), bodyweight reps (weight null/0), weighted bodyweight (added
 * weight), and timed holds (durationSeconds, no reps — e.g. planks). RPE is
 * optional; warm-up sets are flagged so they don't count toward working volume.
 */
final readonly class SetEntry
{
    public function __construct(
        public ?float $weightKg = null,
        public ?int $reps = null,
        public ?float $rpe = null,
        public ?int $durationSeconds = null,
        public bool $isWarmup = false,
        public bool $done = true,
    ) {
        if ($weightKg !== null && $weightKg < 0) {
            throw new InvalidArgumentException('weightKg cannot be negative.');
        }

        if ($reps !== null && $reps < 0) {
            throw new InvalidArgumentException('reps cannot be negative.');
        }

        if ($rpe !== null && ($rpe < 0 || $rpe > 10)) {
            throw new InvalidArgumentException('rpe must be between 0 and 10.');
        }

        if ($durationSeconds !== null && $durationSeconds < 0) {
            throw new InvalidArgumentException('durationSeconds cannot be negative.');
        }
    }

    /** A set that counts toward training volume (not a warm-up). */
    public function isWorking(): bool
    {
        return ! $this->isWarmup && $this->done;
    }

    /** Load moved on this set (kg), 0 when bodyweight-only or timed. */
    public function volumeKg(): float
    {
        if ($this->weightKg === null || $this->reps === null) {
            return 0.0;
        }

        return $this->weightKg * $this->reps;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'weight_kg' => $this->weightKg,
            'reps' => $this->reps,
            'rpe' => $this->rpe,
            'duration_seconds' => $this->durationSeconds,
            'is_warmup' => $this->isWarmup,
            'done' => $this->done,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['weight_kg']) ? (float) $data['weight_kg'] : null,
            isset($data['reps']) ? (int) $data['reps'] : null,
            isset($data['rpe']) ? (float) $data['rpe'] : null,
            isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            (bool) ($data['is_warmup'] ?? false),
            (bool) ($data['done'] ?? true),
        );
    }
}
