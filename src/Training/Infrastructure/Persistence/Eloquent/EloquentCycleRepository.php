<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Persistence\Eloquent;

use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Cadence\Training\Domain\Model\Cycle;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\ValueObject\CycleId;
use Throwable;

final class EloquentCycleRepository implements CycleRepository
{
    public function save(Cycle $cycle): void
    {
        $s = $cycle->toSnapshot();

        try {
            CycleModel::query()->updateOrCreate(['id' => $s['id']], [
                'program_id' => $s['program_id'],
                'tenant_id' => $s['tenant_id'],
                'name' => $s['name'],
                'focus' => $s['focus'],
                'start_date' => $s['start_date'],
                'end_date' => $s['end_date'],
                'phase_index' => $s['phase_index'],
                'status' => $s['status'],
                'sessions' => $s['sessions'],
                'version' => $s['version'],
            ]);
        } catch (Throwable $e) {
            throw new PersistenceFailure('Could not persist the cycle.', 0, $e);
        }
    }

    public function forProgram(string $programId, TenantId $tenant): array
    {
        $cycles = [];
        foreach (
            CycleModel::query()
                ->where('program_id', $programId)
                ->where('tenant_id', $tenant->value)
                ->orderBy('phase_index')
                ->get() as $model
        ) {
            $cycles[] = Cycle::fromSnapshot($this->toSnapshot($model));
        }

        return $cycles;
    }

    public function latestForProgram(string $programId, TenantId $tenant): ?Cycle
    {
        $model = CycleModel::query()
            ->where('program_id', $programId)
            ->where('tenant_id', $tenant->value)
            ->orderByDesc('phase_index')
            ->first();

        return $model instanceof CycleModel ? Cycle::fromSnapshot($this->toSnapshot($model)) : null;
    }

    public function ofId(CycleId $id, TenantId $tenant): ?Cycle
    {
        $model = CycleModel::query()
            ->where('id', $id->value)
            ->where('tenant_id', $tenant->value)
            ->first();

        return $model instanceof CycleModel ? Cycle::fromSnapshot($this->toSnapshot($model)) : null;
    }

    /**
     * @return array{id:string,program_id:string,tenant_id:string,name:string,focus:string,start_date:string,end_date:string,phase_index:int,status:string,sessions:list<array{date:string,type:string,title:string,description:string,target_distance_meters:int|null,target_duration_seconds:int|null,target_pace_seconds_per_km:int|null,steps:list<array{label:string,repeat:int,distance_meters:int|null,duration_seconds:int|null,pace_seconds_per_km:int|null,recovery_seconds:int|null,note:string}>,activity_id:string|null}>,version:int}
     */
    private function toSnapshot(CycleModel $model): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $model->sessions;

        return [
            'id' => $model->id,
            'program_id' => $model->program_id,
            'tenant_id' => $model->tenant_id,
            'name' => (string) $model->name,
            'focus' => (string) $model->focus,
            'start_date' => (string) $model->start_date,
            'end_date' => (string) $model->end_date,
            'phase_index' => (int) $model->phase_index,
            'status' => (string) $model->status,
            'sessions' => array_map(static fn (array $r): array => [
                'date' => (string) $r['date'],
                'type' => (string) $r['type'],
                'title' => (string) $r['title'],
                'description' => (string) $r['description'],
                'target_distance_meters' => isset($r['target_distance_meters']) ? (int) $r['target_distance_meters'] : null,
                'target_duration_seconds' => isset($r['target_duration_seconds']) ? (int) $r['target_duration_seconds'] : null,
                'target_pace_seconds_per_km' => isset($r['target_pace_seconds_per_km']) ? (int) $r['target_pace_seconds_per_km'] : null,
                'steps' => array_map(static fn (array $st): array => [
                    'label' => (string) ($st['label'] ?? ''),
                    'repeat' => isset($st['repeat']) ? max(1, (int) $st['repeat']) : 1,
                    'distance_meters' => isset($st['distance_meters']) ? (int) $st['distance_meters'] : null,
                    'duration_seconds' => isset($st['duration_seconds']) ? (int) $st['duration_seconds'] : null,
                    'pace_seconds_per_km' => isset($st['pace_seconds_per_km']) ? (int) $st['pace_seconds_per_km'] : null,
                    'recovery_seconds' => isset($st['recovery_seconds']) ? (int) $st['recovery_seconds'] : null,
                    'note' => (string) ($st['note'] ?? ''),
                ], is_array($r['steps'] ?? null) ? array_values(array_filter($r['steps'], 'is_array')) : []),
                'activity_id' => isset($r['activity_id']) ? (string) $r['activity_id'] : null,
            ], $rows),
            'version' => (int) $model->version,
        ];
    }
}
