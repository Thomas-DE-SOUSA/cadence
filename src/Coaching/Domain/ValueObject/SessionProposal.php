<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

/**
 * A concrete change to a training day proposed by the coach. Applied only when
 * the athlete accepts. `type` is a session-type string validated at the
 * Training boundary (kept as a string to avoid coupling contexts).
 *
 * @phpstan-type SessionProposalSnapshot array{date:string,type:string,title:string,description:string,target_distance_meters:int|null,target_duration_seconds:int|null,target_pace_seconds_per_km:int|null,rationale:string}
 */
final readonly class SessionProposal
{
    public function __construct(
        public string $date,
        public string $type,
        public string $title,
        public string $description,
        public ?int $targetDistanceMeters,
        public ?int $targetDurationSeconds,
        public ?int $targetPaceSecondsPerKm,
        public string $rationale,
    ) {
    }

    /** @return SessionProposalSnapshot */
    public function toSnapshot(): array
    {
        return [
            'date' => $this->date,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'target_distance_meters' => $this->targetDistanceMeters,
            'target_duration_seconds' => $this->targetDurationSeconds,
            'target_pace_seconds_per_km' => $this->targetPaceSecondsPerKm,
            'rationale' => $this->rationale,
        ];
    }

    /** @param SessionProposalSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            $s['date'],
            $s['type'],
            $s['title'],
            $s['description'],
            $s['target_distance_meters'],
            $s['target_duration_seconds'],
            $s['target_pace_seconds_per_km'],
            $s['rationale'],
        );
    }
}
