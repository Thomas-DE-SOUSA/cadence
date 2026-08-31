<?php

declare(strict_types=1);

namespace Cadence\Strength\Domain\Model;

use Cadence\Strength\Domain\Enum\ExperienceLevel;
use Cadence\Strength\Domain\Enum\GymAccess;
use Cadence\Strength\Domain\Enum\MuscleGroup;
use Cadence\Strength\Domain\Enum\SplitPreference;
use Cadence\Strength\Domain\Enum\StrengthGoal;

/**
 * The athlete's strength profile — the muscu counterpart of the running athlete
 * profile. A single source that personalises the progression views, the cycles
 * and (later) the coach's exercise advice.
 */
final class MuscuProfile
{
    /**
     * @param list<MuscleGroup> $priorities muscle groups to bring up
     * @param list<MuscleGroup> $limitations muscle groups / joints to spare
     */
    public function __construct(
        private readonly string $tenantId,
        private StrengthGoal $goal = StrengthGoal::GENERAL,
        private ExperienceLevel $level = ExperienceLevel::INTERMEDIATE,
        private ?float $bodyweightKg = null,
        private int $weeklyFrequency = 3,
        private SplitPreference $split = SplitPreference::FREE,
        private GymAccess $equipment = GymAccess::FULL_GYM,
        private array $priorities = [],
        private array $limitations = [],
        private string $note = '',
    ) {
    }

    public function goal(): StrengthGoal
    {
        return $this->goal;
    }

    /** @return array<string, mixed> */
    public function toSnapshot(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'goal' => $this->goal->value,
            'level' => $this->level->value,
            'bodyweight_kg' => $this->bodyweightKg,
            'weekly_frequency' => $this->weeklyFrequency,
            'split' => $this->split->value,
            'equipment' => $this->equipment->value,
            'priorities' => array_map(static fn (MuscleGroup $m): string => $m->value, $this->priorities),
            'limitations' => array_map(static fn (MuscleGroup $m): string => $m->value, $this->limitations),
            'note' => $this->note,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromSnapshot(array $data): self
    {
        $toMuscles = static function (mixed $raw): array {
            $out = [];
            foreach (is_array($raw) ? $raw : [] as $v) {
                $case = MuscleGroup::tryFrom((string) $v);
                if ($case !== null) {
                    $out[] = $case;
                }
            }

            return $out;
        };

        return new self(
            (string) $data['tenant_id'],
            StrengthGoal::from((string) ($data['goal'] ?? StrengthGoal::GENERAL->value)),
            ExperienceLevel::from((string) ($data['level'] ?? ExperienceLevel::INTERMEDIATE->value)),
            isset($data['bodyweight_kg']) ? (float) $data['bodyweight_kg'] : null,
            (int) ($data['weekly_frequency'] ?? 3),
            SplitPreference::from((string) ($data['split'] ?? SplitPreference::FREE->value)),
            GymAccess::from((string) ($data['equipment'] ?? GymAccess::FULL_GYM->value)),
            $toMuscles($data['priorities'] ?? []),
            $toMuscles($data['limitations'] ?? []),
            (string) ($data['note'] ?? ''),
        );
    }
}
