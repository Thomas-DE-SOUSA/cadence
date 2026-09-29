<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\ValueObject;

/**
 * An exercise as performed within a session: which exercise, its ordered sets,
 * plus flags for real-world cases — unilateral work (logged per side) and
 * supersets (exercises sharing a group are done back-to-back). The name is
 * snapshotted so history stays stable even if the catalog entry is renamed.
 */
final readonly class PerformedExercise
{
    /** @param list<SetEntry> $sets */
    public function __construct(
        public string $exerciseId,
        public string $name,
        public array $sets,
        public string $note = '',
        public bool $perSide = false,
        public ?int $supersetGroup = null,
    ) {
    }

    /** @return list<SetEntry> */
    public function workingSets(): array
    {
        return array_values(array_filter($this->sets, static fn (SetEntry $s): bool => $s->isWorking()));
    }

    public function volumeKg(): float
    {
        $total = 0.0;
        foreach ($this->workingSets() as $set) {
            $total += $set->volumeKg();
        }

        return $total;
    }

    /**
     * The exercise as actually performed: only the sets ticked done are kept.
     * An unticked set is treated as not done and dropped, so a completed
     * session's log reflects exactly what was executed (the template keeps its
     * full set structure for future planning).
     */
    public function performedOnly(): self
    {
        $done = array_values(array_filter($this->sets, static fn (SetEntry $s): bool => $s->done));

        return new self($this->exerciseId, $this->name, $done, $this->note, $this->perSide, $this->supersetGroup);
    }

    public function hasSets(): bool
    {
        return $this->sets !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'exercise_id' => $this->exerciseId,
            'name' => $this->name,
            'note' => $this->note,
            'per_side' => $this->perSide,
            'superset_group' => $this->supersetGroup,
            'sets' => array_map(static fn (SetEntry $s): array => $s->toArray(), $this->sets),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $rawSets = is_array($data['sets'] ?? null) ? $data['sets'] : [];
        $sets = [];
        foreach ($rawSets as $raw) {
            if (is_array($raw)) {
                $sets[] = SetEntry::fromArray($raw);
            }
        }

        return new self(
            (string) ($data['exercise_id'] ?? ''),
            (string) ($data['name'] ?? ''),
            $sets,
            (string) ($data['note'] ?? ''),
            (bool) ($data['per_side'] ?? false),
            isset($data['superset_group']) ? (int) $data['superset_group'] : null,
        );
    }
}
