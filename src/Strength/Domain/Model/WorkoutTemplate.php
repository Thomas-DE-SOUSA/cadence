<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Model;

use Cadence\Strength\Domain\ValueObject\PerformedExercise;

/**
 * A reusable workout definition ("Push A", "Jambes"…): the exercises and their
 * target sets. Placed onto agenda days as many times as wanted; each placement
 * becomes an independent {@see StrengthSession} that records what was actually
 * done, so progression stays faithful.
 */
final class WorkoutTemplate
{
    /** @param list<PerformedExercise> $exercises target sets live in each exercise */
    public function __construct(
        private readonly string $id,
        private readonly string $tenantId,
        private string $name,
        private array $exercises,
        private int $version = 1,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    /** @return list<PerformedExercise> */
    public function exercises(): array
    {
        return $this->exercises;
    }

    /** @return array<string, mixed> */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'version' => $this->version,
            'exercises' => array_map(static fn (PerformedExercise $e): array => $e->toArray(), $this->exercises),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromSnapshot(array $data): self
    {
        $raw = is_array($data['exercises'] ?? null) ? $data['exercises'] : [];
        $exercises = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $exercises[] = PerformedExercise::fromArray($item);
            }
        }

        return new self(
            (string) $data['id'],
            (string) $data['tenant_id'],
            (string) ($data['name'] ?? ''),
            $exercises,
            (int) ($data['version'] ?? 1),
        );
    }
}
