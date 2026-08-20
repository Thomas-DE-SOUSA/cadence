<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Model;

use Cadence\Activity\Domain\ValueObject\Distance;
use Cadence\Activity\Domain\ValueObject\Duration;
use Cadence\Activity\Domain\ValueObject\Elevation;
use Cadence\Activity\Domain\ValueObject\Pace;

/** One kilometre (or segment) of an Activity. */
final class Split
{
    private function __construct(
        public readonly int $index,
        public readonly Distance $distance,
        public readonly Duration $duration,
        public readonly Elevation $elevation,
    ) {
    }

    public static function record(int $index, Distance $distance, Duration $duration, Elevation $elevation): self
    {
        return new self($index, $distance, $duration, $elevation);
    }

    public function pace(): Pace
    {
        return Pace::fromDistanceAndDuration($this->distance, $this->duration);
    }

    /** @return array{index:int,distance_meters:int,duration_seconds:int,elevation_meters:int} */
    public function toSnapshot(): array
    {
        return [
            'index' => $this->index,
            'distance_meters' => $this->distance->meters,
            'duration_seconds' => $this->duration->seconds,
            'elevation_meters' => $this->elevation->meters,
        ];
    }

    /** @param array{index:int,distance_meters:int,duration_seconds:int,elevation_meters:int} $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            $s['index'],
            Distance::fromStorage($s['distance_meters']),
            Duration::fromStorage($s['duration_seconds']),
            Elevation::ofMeters($s['elevation_meters']),
        );
    }
}
