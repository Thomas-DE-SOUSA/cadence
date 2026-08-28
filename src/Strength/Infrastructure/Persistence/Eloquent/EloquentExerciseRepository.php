<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Cadence\Strength\Domain\Model\Exercise;
use Cadence\Strength\Domain\Port\ExerciseRepository;
use Throwable;

final class EloquentExerciseRepository implements ExerciseRepository
{
    public function save(Exercise $exercise): void
    {
        $s = $exercise->toSnapshot();

        try {
            ExerciseModel::query()->updateOrCreate(['id' => $s['id']], [
                'tenant_id' => $s['tenant_id'],
                'name' => $s['name'],
                'primary_muscle' => $s['primary_muscle'],
                'equipment' => $s['equipment'],
                'is_custom' => $s['is_custom'],
            ]);
        } catch (Throwable $e) {
            throw new PersistenceFailure('Could not persist the exercise.', 0, $e);
        }
    }

    public function ofId(string $id, TenantId $tenant): ?Exercise
    {
        $model = ExerciseModel::query()
            ->where('id', $id)
            ->where(function ($q) use ($tenant): void {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->value);
            })
            ->first();

        return $model instanceof ExerciseModel ? $this->toDomain($model) : null;
    }

    public function forTenant(TenantId $tenant): array
    {
        $models = ExerciseModel::query()
            ->where(function ($q) use ($tenant): void {
                $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->value);
            })
            ->orderBy('name')
            ->get();

        return array_values($models->map(fn (ExerciseModel $m): Exercise => $this->toDomain($m))->all());
    }

    private function toDomain(ExerciseModel $m): Exercise
    {
        return Exercise::fromSnapshot([
            'id' => $m->id,
            'tenant_id' => $m->tenant_id,
            'name' => $m->name,
            'primary_muscle' => $m->primary_muscle,
            'equipment' => $m->equipment,
            'is_custom' => $m->is_custom,
        ]);
    }
}
