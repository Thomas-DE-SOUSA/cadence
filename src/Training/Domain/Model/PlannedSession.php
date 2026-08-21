<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Model;

use Cadence\Training\Domain\Enum\SessionType;

/**
 * One planned training session on a given day. `activityId` links the actual
 * run logged for that day, once assigned.
 *
 * @phpstan-type PlannedSessionSnapshot array{date:string,type:string,title:string,description:string,target_distance_meters:int|null,target_duration_seconds:int|null,target_pace_seconds_per_km:int|null,activity_id:string|null}
 */
final class PlannedSession
{
    public function __construct(
        public readonly string $date,
        public readonly SessionType $type,
        public readonly string $title,
        public readonly string $description,
        public readonly ?int $targetDistanceMeters,
        public readonly ?int $targetDurationSeconds,
        public readonly ?int $targetPaceSecondsPerKm,
        public readonly ?string $activityId = null,
    ) {
    }

    public function withActivity(?string $activityId): self
    {
        return new self(
            $this->date,
            $this->type,
            $this->title,
            $this->description,
            $this->targetDistanceMeters,
            $this->targetDurationSeconds,
            $this->targetPaceSecondsPerKm,
            $activityId,
        );
    }

    /** @return PlannedSessionSnapshot */
    public function toSnapshot(): array
    {
        return [
            'date' => $this->date,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'target_distance_meters' => $this->targetDistanceMeters,
            'target_duration_seconds' => $this->targetDurationSeconds,
            'target_pace_seconds_per_km' => $this->targetPaceSecondsPerKm,
            'activity_id' => $this->activityId,
        ];
    }

    /** @param PlannedSessionSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            $s['date'],
            SessionType::from($s['type']),
            $s['title'],
            $s['description'],
            $s['target_distance_meters'],
            $s['target_duration_seconds'],
            $s['target_pace_seconds_per_km'],
            $s['activity_id'] ?? null,
        );
    }
}
