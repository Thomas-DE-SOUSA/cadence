<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Model;

use Cadence\Training\Domain\Enum\SessionType;
use Cadence\Training\Domain\ValueObject\SessionStep;

/**
 * One planned training session. `date` is the internal week anchor (cycle start
 * + offset) used to group sessions into weeks and to key manual links.
 * `suggestedDate` is what the UI shows: a soft day hint for athletes on a fixed
 * schedule, or null for flexible athletes who train on no fixed day. `steps` is
 * the structured breakdown; `activityId` links a manually-attached run.
 *
 * @phpstan-import-type SessionStepSnapshot from SessionStep
 *
 * @phpstan-type PlannedSessionSnapshot array{date:string,suggested_date?:string|null,type:string,title:string,description:string,target_distance_meters:int|null,target_duration_seconds:int|null,target_pace_seconds_per_km:int|null,steps:list<SessionStepSnapshot>,activity_id:string|null}
 */
final class PlannedSession
{
    /**
     * @param list<SessionStep> $steps
     */
    public function __construct(
        public readonly string $date,
        public readonly SessionType $type,
        public readonly string $title,
        public readonly string $description,
        public readonly ?int $targetDistanceMeters,
        public readonly ?int $targetDurationSeconds,
        public readonly ?int $targetPaceSecondsPerKm,
        public readonly array $steps = [],
        public readonly ?string $activityId = null,
        public readonly ?string $suggestedDate = null,
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
            $this->steps,
            $activityId,
            $this->suggestedDate,
        );
    }

    /** @return PlannedSessionSnapshot */
    public function toSnapshot(): array
    {
        return [
            'date' => $this->date,
            'suggested_date' => $this->suggestedDate,
            'type' => $this->type->value,
            'title' => $this->title,
            'description' => $this->description,
            'target_distance_meters' => $this->targetDistanceMeters,
            'target_duration_seconds' => $this->targetDurationSeconds,
            'target_pace_seconds_per_km' => $this->targetPaceSecondsPerKm,
            'steps' => array_map(static fn (SessionStep $s): array => $s->toSnapshot(), $this->steps),
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
            array_map(static fn (array $row): SessionStep => SessionStep::fromSnapshot($row), $s['steps']),
            $s['activity_id'] ?? null,
            // Older cycles predate suggested_date: fall back to the anchor date
            // so fixed-schedule athletes keep their day hint.
            array_key_exists('suggested_date', $s) ? $s['suggested_date'] : $s['date'],
        );
    }
}
