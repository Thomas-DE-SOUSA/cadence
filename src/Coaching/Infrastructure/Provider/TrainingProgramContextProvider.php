<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Provider;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Domain\Port\ProgramContextProvider;
use Cadence\Coaching\Domain\Service\VdotCalculator;
use Cadence\Coaching\Domain\ValueObject\PlannedDay;
use Cadence\Coaching\Domain\ValueObject\ProgramDay;
use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Enum\ObjectiveType;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Domain\ValueObject\CycleId;
use Cadence\Training\Domain\ValueObject\ProgramId;

final class TrainingProgramContextProvider implements ProgramContextProvider
{
    public function __construct(
        private readonly TrainingProgramRepository $programs,
        private readonly CycleRepository $cycles,
        private readonly VdotCalculator $vdot,
    ) {
    }

    public function forDay(string $programId, string $cycleId, string $sessionDate, TenantId $tenant): ?ProgramDay
    {
        $program = $this->programs->ofId(ProgramId::fromString($programId), $tenant);
        if ($program === null) {
            return null;
        }

        $cycle = $this->cycles->ofId(CycleId::fromString($cycleId), $tenant);
        if ($cycle === null || $cycle->programId() !== $programId) {
            return null;
        }

        $session = null;
        foreach ($cycle->toSnapshot()['sessions'] as $candidate) {
            if ($candidate['date'] === $sessionDate) {
                $session = $candidate;
                break;
            }
        }
        if ($session === null) {
            return null;
        }

        $snapshot = $program->toSnapshot();

        $targetVdot = null;
        foreach ($program->objectives() as $objective) {
            if ($objective->type === ObjectiveType::RACE_TIME && $objective->targetDistanceMeters !== null && $objective->targetSeconds !== null) {
                $candidate = $this->vdot->vdot($objective->targetDistanceMeters, $objective->targetSeconds);
                $targetVdot = $targetVdot === null ? $candidate : max($targetVdot, $candidate);
            }
        }

        $day = new PlannedDay(
            $sessionDate,
            $session['type'],
            $session['title'],
            $session['description'],
            $session['target_distance_meters'],
            $session['target_duration_seconds'],
            $session['target_pace_seconds_per_km'],
            $this->actualSummary($tenant, $sessionDate, $session['activity_id']),
        );

        return new ProgramDay(
            $snapshot['name'],
            $snapshot['goal'],
            $snapshot['target_race_name'],
            $snapshot['target_race_date'],
            $targetVdot !== null ? round($targetVdot, 1) : null,
            $day,
        );
    }

    private function actualSummary(TenantId $tenant, string $date, ?string $activityId): ?string
    {
        $query = ActivityModel::query()->where('tenant_id', $tenant->value);
        $model = $activityId !== null
            ? $query->where('id', $activityId)->first()
            : $query->whereRaw('substr(occurred_at, 1, 10) = ?', [$date])->first();

        if (! $model instanceof ActivityModel) {
            return null;
        }

        $distance = (int) $model->distance_meters;
        $seconds = (int) $model->moving_seconds;
        if ($distance <= 0 || $seconds <= 0) {
            return null;
        }

        $pace = (int) round($seconds / ($distance / 1000));

        return sprintf('%.2f km à %d:%02d/km', $distance / 1000, intdiv($pace, 60), $pace % 60);
    }
}
