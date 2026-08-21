<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Persistence\Eloquent;

use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Outbox\OutboxEventModel;
use Cadence\Shared\Infrastructure\Persistence\ConcurrencyException;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Cadence\Training\Domain\Model\TrainingProgram;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Domain\ValueObject\ProgramId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

final class EloquentTrainingProgramRepository implements TrainingProgramRepository
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function save(TrainingProgram $program, array $events): void
    {
        $snapshot = $program->toSnapshot();

        try {
            DB::transaction(function () use ($snapshot, $events): void {
                $attributes = [
                    'tenant_id' => $snapshot['tenant_id'],
                    'name' => $snapshot['name'],
                    'goal' => $snapshot['goal'],
                    'target_race_name' => $snapshot['target_race_name'],
                    'target_race_date' => $snapshot['target_race_date'],
                    'start_date' => $snapshot['start_date'],
                    'end_date' => $snapshot['end_date'],
                    'priority' => $snapshot['priority'],
                    'status' => $snapshot['status'],
                    'plan_key' => $snapshot['plan_key'],
                    'objectives' => $snapshot['objectives'],
                    'assigned_activity_ids' => $snapshot['assigned_activity_ids'],
                    'version' => $snapshot['version'],
                ];

                if ($snapshot['version'] === 1) {
                    TrainingProgramModel::query()->create(['id' => $snapshot['id'], ...$attributes]);
                } else {
                    $affected = TrainingProgramModel::query()
                        ->where('id', $snapshot['id'])
                        ->where('tenant_id', $snapshot['tenant_id'])
                        ->where('version', $snapshot['version'] - 1)
                        ->update($attributes);

                    if ($affected === 0) {
                        throw new ConcurrencyException("Program {$snapshot['id']} was modified concurrently.");
                    }
                }

                $ordinal = 0;
                foreach ($events as $event) {
                    OutboxEventModel::query()->create([
                        'id' => (string) Str::orderedUuid(),
                        'aggregate_id' => $event->aggregateId,
                        'aggregate_type' => 'training_program',
                        'tenant_id' => $snapshot['tenant_id'],
                        'event_name' => $event->name(),
                        'payload' => $event->payload(),
                        'version' => $snapshot['version'] + $ordinal,
                        'occurred_at' => $event->occurredAt->format(DATE_ATOM),
                        'published' => false,
                    ]);
                    $ordinal++;
                }
            });
        } catch (ConcurrencyException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logger->error('Failed to persist training program', [
                'aggregate_id' => $snapshot['id'],
                'tenant_id' => $snapshot['tenant_id'],
                'exception' => $e->getMessage(),
            ]);

            throw new PersistenceFailure('Could not persist the program.', 0, $e);
        }
    }

    public function ofId(ProgramId $id, TenantId $tenant): ?TrainingProgram
    {
        $model = TrainingProgramModel::query()
            ->where('id', $id->value)
            ->where('tenant_id', $tenant->value)
            ->first();

        if (! $model instanceof TrainingProgramModel) {
            return null;
        }

        return TrainingProgram::fromSnapshot($this->toSnapshot($model));
    }

    public function allForTenant(TenantId $tenant): array
    {
        $programs = [];
        foreach (
            TrainingProgramModel::query()
                ->where('tenant_id', $tenant->value)
                ->orderByDesc('start_date')
                ->get() as $model
        ) {
            $programs[] = TrainingProgram::fromSnapshot($this->toSnapshot($model));
        }

        return $programs;
    }

    /**
     * @return array{id:string,tenant_id:string,name:string,goal:string,target_race_name:string,target_race_date:string|null,start_date:string,end_date:string|null,priority:string,status:string,plan_key:string|null,objectives:list<array{id:string,type:string,label:string,target_distance_meters:int|null,target_seconds:int|null,target_pace_seconds_per_km:float|null,target_count:int|null}>,assigned_activity_ids:list<string>,version:int}
     */
    private function toSnapshot(TrainingProgramModel $model): array
    {
        /** @var list<array<string, mixed>> $objectiveRows */
        $objectiveRows = $model->objectives;
        /** @var list<mixed> $assignedRows */
        $assignedRows = $model->assigned_activity_ids;

        return [
            'id' => $model->id,
            'tenant_id' => $model->tenant_id,
            'name' => (string) $model->name,
            'goal' => (string) $model->goal,
            'target_race_name' => (string) $model->target_race_name,
            'target_race_date' => $model->target_race_date !== null ? (string) $model->target_race_date : null,
            'start_date' => (string) $model->start_date,
            'end_date' => $model->end_date !== null ? (string) $model->end_date : null,
            'priority' => (string) $model->priority,
            'status' => (string) $model->status,
            'plan_key' => $model->plan_key !== null ? (string) $model->plan_key : null,
            'objectives' => array_map(static fn (array $r): array => [
                'id' => (string) $r['id'],
                'type' => (string) $r['type'],
                'label' => (string) $r['label'],
                'target_distance_meters' => isset($r['target_distance_meters']) ? (int) $r['target_distance_meters'] : null,
                'target_seconds' => isset($r['target_seconds']) ? (int) $r['target_seconds'] : null,
                'target_pace_seconds_per_km' => isset($r['target_pace_seconds_per_km']) ? (float) $r['target_pace_seconds_per_km'] : null,
                'target_count' => isset($r['target_count']) ? (int) $r['target_count'] : null,
            ], $objectiveRows),
            'assigned_activity_ids' => array_map(static fn (mixed $x): string => (string) $x, $assignedRows),
            'version' => (int) $model->version,
        ];
    }
}
