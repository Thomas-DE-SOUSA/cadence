<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Provider;

use Cadence\Coaching\Domain\Port\AthleteGoalProvider;
use Cadence\Coaching\Domain\ValueObject\ProgramGoal;
use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Port\TrainingProgramRepository;

/**
 * Reads the current running goal from the Training context: the most recently
 * started program (allForTenant is ordered by start_date DESC), or none.
 */
final readonly class TrainingGoalProvider implements AthleteGoalProvider
{
    public function __construct(private TrainingProgramRepository $programs)
    {
    }

    public function currentGoal(TenantId $tenant): ?ProgramGoal
    {
        $programs = $this->programs->allForTenant($tenant);
        if ($programs === []) {
            return null;
        }

        $snapshot = $programs[0]->toSnapshot();

        return new ProgramGoal(
            $snapshot['name'],
            $snapshot['goal'],
            $snapshot['target_race_name'],
            $snapshot['target_race_date'],
        );
    }
}
