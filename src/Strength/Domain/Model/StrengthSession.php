<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Model;

use Cadence\Strength\Domain\Enum\WorkoutStatus;
use Cadence\Strength\Domain\ValueObject\PerformedExercise;

/**
 * A workout placed on a day of the agenda: which exercises with their (actual)
 * sets, whether it's still planned or done, and the template it came from (if
 * any). The aggregate that owns a session's consistency and snapshots to/from
 * persistence.
 */
final class StrengthSession
{
    /** @param list<PerformedExercise> $exercises */
    public function __construct(
        private readonly string $id,
        private readonly string $tenantId,
        private string $date,
        private string $title,
        private string $note,
        private ?int $durationSeconds,
        private array $exercises,
        private WorkoutStatus $status = WorkoutStatus::DONE,
        private ?string $templateId = null,
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

    public function status(): WorkoutStatus
    {
        return $this->status;
    }

    public function totalSets(): int
    {
        $count = 0;
        foreach ($this->exercises as $exercise) {
            $count += count($exercise->workingSets());
        }

        return $count;
    }

    public function totalVolumeKg(): float
    {
        $total = 0.0;
        foreach ($this->exercises as $exercise) {
            $total += $exercise->volumeKg();
        }

        return $total;
    }

    /** @return array<string, mixed> */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'date' => $this->date,
            'title' => $this->title,
            'note' => $this->note,
            'duration_seconds' => $this->durationSeconds,
            'status' => $this->status->value,
            'template_id' => $this->templateId,
            'version' => $this->version,
            'exercises' => array_map(static fn (PerformedExercise $e): array => $e->toArray(), $this->exercises),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromSnapshot(array $data): self
    {
        $rawExercises = is_array($data['exercises'] ?? null) ? $data['exercises'] : [];
        $exercises = [];
        foreach ($rawExercises as $raw) {
            if (is_array($raw)) {
                $exercises[] = PerformedExercise::fromArray($raw);
            }
        }

        return new self(
            (string) $data['id'],
            (string) $data['tenant_id'],
            (string) $data['date'],
            (string) ($data['title'] ?? ''),
            (string) ($data['note'] ?? ''),
            isset($data['duration_seconds']) ? (int) $data['duration_seconds'] : null,
            $exercises,
            WorkoutStatus::from((string) ($data['status'] ?? WorkoutStatus::DONE->value)),
            isset($data['template_id']) ? (string) $data['template_id'] : null,
            (int) ($data['version'] ?? 1),
        );
    }
}
