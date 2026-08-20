<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Persistence\Eloquent;

use Cadence\Activity\Domain\Model\Activity;
use Cadence\Activity\Domain\Port\ActivityRepository;
use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Outbox\OutboxEventModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentActivityRepository implements ActivityRepository
{
    public function save(Activity $activity, array $events): void
    {
        $snapshot = $activity->toSnapshot();

        DB::transaction(function () use ($snapshot, $events): void {
            ActivityModel::query()->updateOrCreate(
                ['id' => $snapshot['id']],
                [
                    'tenant_id' => $snapshot['tenant_id'],
                    'occurred_at' => $snapshot['occurred_at'],
                    'source' => $snapshot['source'],
                    'distance_meters' => $snapshot['distance_meters'],
                    'moving_seconds' => $snapshot['moving_seconds'],
                    'elapsed_seconds' => $snapshot['elapsed_seconds'],
                    'elevation_gain_meters' => $snapshot['elevation_gain_meters'],
                    'average_pace_seconds_per_km' => $snapshot['average_pace_seconds_per_km'],
                    'splits' => $snapshot['splits'],
                    'best_efforts' => $snapshot['best_efforts'],
                    'version' => $snapshot['version'],
                ],
            );

            foreach ($events as $event) {
                OutboxEventModel::query()->create([
                    'id' => (string) Str::orderedUuid(),
                    'aggregate_id' => $event->aggregateId,
                    'aggregate_type' => 'activity',
                    'tenant_id' => $snapshot['tenant_id'],
                    'event_name' => $event->name(),
                    'payload' => $event->payload(),
                    'version' => $snapshot['version'],
                    'occurred_at' => $event->occurredAt->format(DATE_ATOM),
                    'published' => false,
                ]);
            }
        });
    }

    public function ofId(ActivityId $id, TenantId $tenant): ?Activity
    {
        $model = ActivityModel::query()
            ->where('id', $id->value)
            ->where('tenant_id', $tenant->value)
            ->first();

        if (! $model instanceof ActivityModel) {
            return null;
        }

        return Activity::fromSnapshot($this->toSnapshot($model));
    }

    /**
     * @return array{id:string,tenant_id:string,occurred_at:string,source:string,distance_meters:int,moving_seconds:int,elapsed_seconds:int,elevation_gain_meters:int,average_pace_seconds_per_km:float,splits:list<array{index:int,distance_meters:int,duration_seconds:int,elevation_meters:int}>,best_efforts:list<array{label:string,distance_meters:int,duration_seconds:int,is_personal_record:bool}>,version:int}
     */
    private function toSnapshot(ActivityModel $model): array
    {
        /** @var list<array<string, mixed>> $splitRows */
        $splitRows = $model->splits;
        /** @var list<array<string, mixed>> $effortRows */
        $effortRows = $model->best_efforts;

        return [
            'id' => $model->id,
            'tenant_id' => $model->tenant_id,
            'occurred_at' => (string) $model->occurred_at,
            'source' => (string) $model->source,
            'distance_meters' => $model->distance_meters,
            'moving_seconds' => $model->moving_seconds,
            'elapsed_seconds' => $model->elapsed_seconds,
            'elevation_gain_meters' => $model->elevation_gain_meters,
            'average_pace_seconds_per_km' => $model->average_pace_seconds_per_km,
            'splits' => array_map(static fn (array $r): array => [
                'index' => (int) $r['index'],
                'distance_meters' => (int) $r['distance_meters'],
                'duration_seconds' => (int) $r['duration_seconds'],
                'elevation_meters' => (int) $r['elevation_meters'],
            ], $splitRows),
            'best_efforts' => array_map(static fn (array $r): array => [
                'label' => (string) $r['label'],
                'distance_meters' => (int) $r['distance_meters'],
                'duration_seconds' => (int) $r['duration_seconds'],
                'is_personal_record' => (bool) $r['is_personal_record'],
            ], $effortRows),
            'version' => $model->version,
        ];
    }
}
