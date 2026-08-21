<?php

declare(strict_types=1);

namespace Cadence\Training\Domain\Model;

use Cadence\Training\Domain\Enum\ObjectiveType;

/**
 * A measurable target inside a program. Its target fields are used per type
 * (see ObjectiveType); unused ones are null.
 *
 * @phpstan-type ObjectiveSnapshot array{id:string,type:string,label:string,target_distance_meters:int|null,target_seconds:int|null,target_pace_seconds_per_km:float|null,target_count:int|null}
 */
final class Objective
{
    public function __construct(
        public readonly string $id,
        public readonly ObjectiveType $type,
        public readonly string $label,
        public readonly ?int $targetDistanceMeters,
        public readonly ?int $targetSeconds,
        public readonly ?float $targetPaceSecondsPerKm,
        public readonly ?int $targetCount,
    ) {
    }

    /** @return ObjectiveSnapshot */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'label' => $this->label,
            'target_distance_meters' => $this->targetDistanceMeters,
            'target_seconds' => $this->targetSeconds,
            'target_pace_seconds_per_km' => $this->targetPaceSecondsPerKm,
            'target_count' => $this->targetCount,
        ];
    }

    /** @param ObjectiveSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            $s['id'],
            ObjectiveType::from($s['type']),
            $s['label'],
            $s['target_distance_meters'],
            $s['target_seconds'],
            $s['target_pace_seconds_per_km'],
            $s['target_count'],
        );
    }
}
