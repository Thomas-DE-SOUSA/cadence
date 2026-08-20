<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Model;

use Cadence\Activity\Domain\ValueObject\Distance;
use Cadence\Activity\Domain\ValueObject\Duration;
use Cadence\Activity\Domain\ValueObject\Pace;

/** A rolling best effort recorded inside an Activity (e.g. best 5 km). */
final class BestEffort
{
    private function __construct(
        public readonly string $label,
        public readonly Distance $distance,
        public readonly Duration $duration,
        public readonly bool $isPersonalRecord,
    ) {
    }

    public static function record(string $label, Distance $distance, Duration $duration, bool $isPersonalRecord): self
    {
        return new self($label, $distance, $duration, $isPersonalRecord);
    }

    public function pace(): Pace
    {
        return Pace::fromDistanceAndDuration($this->distance, $this->duration);
    }

    /** @return array{label:string,distance_meters:int,duration_seconds:int,is_personal_record:bool} */
    public function toSnapshot(): array
    {
        return [
            'label' => $this->label,
            'distance_meters' => $this->distance->meters,
            'duration_seconds' => $this->duration->seconds,
            'is_personal_record' => $this->isPersonalRecord,
        ];
    }

    /** @param array{label:string,distance_meters:int,duration_seconds:int,is_personal_record:bool} $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            $s['label'],
            Distance::fromStorage($s['distance_meters']),
            Duration::fromStorage($s['duration_seconds']),
            $s['is_personal_record'],
        );
    }
}
