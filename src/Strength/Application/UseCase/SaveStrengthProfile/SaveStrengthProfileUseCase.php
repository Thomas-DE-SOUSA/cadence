<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\SaveStrengthProfile;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Strength\Domain\Enum\ExperienceLevel;
use Cadence\Strength\Domain\Enum\GymAccess;
use Cadence\Strength\Domain\Enum\MuscleGroup;
use Cadence\Strength\Domain\Enum\SplitPreference;
use Cadence\Strength\Domain\Enum\StrengthGoal;
use Cadence\Strength\Domain\Model\StrengthProfile;
use Cadence\Strength\Domain\Port\StrengthProfileRepository;

final readonly class SaveStrengthProfileUseCase
{
    public function __construct(private StrengthProfileRepository $profiles)
    {
    }

    public function execute(SaveStrengthProfileInput $input, ExecutionContext $context): void
    {
        $toMuscles = static function (array $values): array {
            $out = [];
            foreach ($values as $v) {
                $case = MuscleGroup::tryFrom((string) $v);
                if ($case !== null) {
                    $out[] = $case;
                }
            }

            return $out;
        };

        $this->profiles->save(new StrengthProfile(
            $context->tenant->value,
            StrengthGoal::from($input->goal),
            ExperienceLevel::from($input->level),
            $input->bodyweightKg,
            max(1, min(7, $input->weeklyFrequency)),
            SplitPreference::from($input->split),
            GymAccess::from($input->equipment),
            $toMuscles($input->priorities),
            $toMuscles($input->limitations),
            trim($input->note),
        ));
    }
}
