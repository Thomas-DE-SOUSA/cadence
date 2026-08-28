<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Cadence\Strength\Domain\Model\WorkoutTemplate;
use Cadence\Strength\Domain\Port\WorkoutTemplateRepository;
use Throwable;

final class EloquentWorkoutTemplateRepository implements WorkoutTemplateRepository
{
    public function save(WorkoutTemplate $template): void
    {
        $s = $template->toSnapshot();

        try {
            WorkoutTemplateModel::query()->updateOrCreate(['id' => $s['id']], [
                'tenant_id' => $s['tenant_id'],
                'name' => $s['name'],
                'version' => $s['version'],
                'exercises' => $s['exercises'],
            ]);
        } catch (Throwable $e) {
            throw new PersistenceFailure('Could not persist the workout template.', 0, $e);
        }
    }

    public function ofId(string $id, TenantId $tenant): ?WorkoutTemplate
    {
        $model = WorkoutTemplateModel::query()
            ->where('id', $id)
            ->where('tenant_id', $tenant->value)
            ->first();

        return $model instanceof WorkoutTemplateModel ? $this->toDomain($model) : null;
    }

    public function delete(string $id, TenantId $tenant): void
    {
        WorkoutTemplateModel::query()->where('id', $id)->where('tenant_id', $tenant->value)->delete();
    }

    public function forTenant(TenantId $tenant): array
    {
        $models = WorkoutTemplateModel::query()
            ->where('tenant_id', $tenant->value)
            ->orderBy('name')
            ->get();

        return array_values($models->map(fn (WorkoutTemplateModel $m): WorkoutTemplate => $this->toDomain($m))->all());
    }

    private function toDomain(WorkoutTemplateModel $m): WorkoutTemplate
    {
        return WorkoutTemplate::fromSnapshot([
            'id' => $m->id,
            'tenant_id' => $m->tenant_id,
            'name' => $m->name,
            'version' => $m->version,
            'exercises' => $m->exercises,
        ]);
    }
}
