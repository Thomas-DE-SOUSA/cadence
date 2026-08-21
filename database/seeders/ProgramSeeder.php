<?php

declare(strict_types=1);

namespace Database\Seeders;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Application\UseCase\AssignActivity\AssignActivityUseCase;
use Cadence\Training\Application\UseCase\CreateProgram\CreateProgramInput;
use Cadence\Training\Application\UseCase\CreateProgram\CreateProgramUseCase;
use Cadence\Training\Application\UseCase\CreateProgram\ObjectiveInput;
use Cadence\Training\Infrastructure\Persistence\Eloquent\TrainingProgramModel;
use Illuminate\Database\Seeder;

/** Seeds Thomas' Odysséa 10K program with its objectives and assigns his runs. */
final class ProgramSeeder extends Seeder
{
    private const TENANT = 'tenant-thomas';

    public function run(): void
    {
        if (TrainingProgramModel::query()->where('tenant_id', self::TENANT)->exists()) {
            return;
        }

        $context = new ExecutionContext(TenantId::fromString(self::TENANT));

        $programId = app(CreateProgramUseCase::class)->execute(new CreateProgramInput(
            name: 'Prépa Odysséa 10K',
            goal: 'Passer sous 40 min au 10 km',
            targetRaceName: 'Odysséa Paris 10 km',
            targetRaceDate: '2026-10-04',
            startDate: '2026-08-20',
            endDate: '2026-10-04',
            priority: 'A',
            objectives: [
                new ObjectiveInput('RACE_TIME', '10 km sous 40:00', targetDistanceMeters: 10000, targetSeconds: 2400),
                new ObjectiveInput('PACE_OVER_DISTANCE', 'Tenir 4:00/km sur 5 km', targetDistanceMeters: 5000, targetPaceSecondsPerKm: 240.0),
                new ObjectiveInput('LONGEST_RUN', 'Une sortie longue de 12 km', targetDistanceMeters: 12000),
                new ObjectiveInput('TOTAL_VOLUME', '150 km sur le bloc', targetDistanceMeters: 150000),
                new ObjectiveInput('SESSION_COUNT', '20 séances', targetCount: 20),
            ],
        ), $context);

        $assign = app(AssignActivityUseCase::class);
        foreach (ActivityModel::query()->where('tenant_id', self::TENANT)->pluck('id') as $activityId) {
            $assign->execute($programId, (string) $activityId, $context);
        }
    }
}
