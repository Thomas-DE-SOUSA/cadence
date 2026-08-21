<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\ValueObject;

/**
 * One structured step of a session: a warm-up, a rep block, a recovery or a
 * cool-down. `repeat` > 1 expresses sets like "2×8 min"; `recoverySeconds` is
 * the recovery taken after each rep.
 *
 * @phpstan-type SessionStepSnapshot array{label:string,repeat:int,distance_meters:int|null,duration_seconds:int|null,pace_seconds_per_km:int|null,recovery_seconds:int|null,note:string}
 */
final readonly class SessionStep
{
    public function __construct(
        public string $label,
        public int $repeat,
        public ?int $distanceMeters,
        public ?int $durationSeconds,
        public ?int $paceSecondsPerKm,
        public ?int $recoverySeconds,
        public string $note,
    ) {
    }

    /** @return SessionStepSnapshot */
    public function toSnapshot(): array
    {
        return [
            'label' => $this->label,
            'repeat' => $this->repeat,
            'distance_meters' => $this->distanceMeters,
            'duration_seconds' => $this->durationSeconds,
            'pace_seconds_per_km' => $this->paceSecondsPerKm,
            'recovery_seconds' => $this->recoverySeconds,
            'note' => $this->note,
        ];
    }

    /** @param SessionStepSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            $s['label'],
            max(1, $s['repeat']),
            $s['distance_meters'],
            $s['duration_seconds'],
            $s['pace_seconds_per_km'],
            $s['recovery_seconds'],
            $s['note'],
        );
    }
}
