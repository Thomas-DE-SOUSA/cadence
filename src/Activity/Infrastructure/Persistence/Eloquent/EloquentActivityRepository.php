<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Persistence\Eloquent;

use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Domain\Model\Activity;
use Cadence\Activity\Domain\Port\ActivityRepository;
use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Outbox\OutboxEventModel;
use Cadence\Shared\Infrastructure\Persistence\ConcurrencyException;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

final class EloquentActivityRepository implements ActivityRepository
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function save(Activity $activity, array $events): void
    {
        $snapshot = $activity->toSnapshot();

        try {
            DB::transaction(function () use ($snapshot, $events): void {
                $attributes = [
                    'tenant_id' => $snapshot['tenant_id'],
                    'occurred_at' => $snapshot['occurred_at'],
                    'source' => $snapshot['source'],
                    'external_id' => $snapshot['external_id'],
                    'distance_meters' => $snapshot['distance_meters'],
                    'moving_seconds' => $snapshot['moving_seconds'],
                    'elapsed_seconds' => $snapshot['elapsed_seconds'],
                    'elevation_gain_meters' => $snapshot['elevation_gain_meters'],
                    'average_pace_seconds_per_km' => $snapshot['average_pace_seconds_per_km'],
                    'splits' => $snapshot['splits'],
                    'best_efforts' => $snapshot['best_efforts'],
                    'version' => $snapshot['version'],
                ];

                if ($snapshot['version'] === 1) {
                    ActivityModel::query()->create(['id' => $snapshot['id'], ...$attributes]);
                } else {
                    // Optimistic lock: only update the row still at the previous version.
                    $affected = ActivityModel::query()
                        ->where('id', $snapshot['id'])
                        ->where('tenant_id', $snapshot['tenant_id'])
                        ->where('version', $snapshot['version'] - 1)
                        ->update($attributes);

                    if ($affected === 0) {
                        throw new ConcurrencyException("Activity {$snapshot['id']} was modified concurrently.");
                    }
                }

                $ordinal = 0;
                foreach ($events as $event) {
                    OutboxEventModel::query()->create([
                        'id' => (string) Str::orderedUuid(),
                        'aggregate_id' => $event->aggregateId,
                        'aggregate_type' => 'activity',
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
            $this->logger->error('Failed to persist activity', [
                'aggregate_id' => $snapshot['id'],
                'tenant_id' => $snapshot['tenant_id'],
                'exception' => $e->getMessage(),
            ]);

            throw new PersistenceFailure('Could not persist the activity.', 0, $e);
        }
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

    public function delete(ActivityId $id, TenantId $tenant): void
    {
        ActivityModel::query()
            ->where('id', $id->value)
            ->where('tenant_id', $tenant->value)
            ->delete();
    }

    public function existsForExternalId(TenantId $tenant, ActivitySource $source, string $externalId): bool
    {
        return ActivityModel::query()
            ->where('tenant_id', $tenant->value)
            ->where('source', $source->value)
            ->where('external_id', $externalId)
            ->exists();
    }

    public function hasActivityOn(
        TenantId $tenant,
        string $day,
        int $minDistanceMeters,
        int $maxDistanceMeters,
        int $minMovingSeconds,
        int $maxMovingSeconds,
    ): bool {
        return ActivityModel::query()
            ->where('tenant_id', $tenant->value)
            ->where('occurred_at', 'like', $day.'%')
            ->whereBetween('distance_meters', [$minDistanceMeters, $maxDistanceMeters])
            ->whereBetween('moving_seconds', [$minMovingSeconds, $maxMovingSeconds])
            ->exists();
    }

    /**
     * @return array{id:string,tenant_id:string,occurred_at:string,source:string,external_id:string|null,distance_meters:int,moving_seconds:int,elapsed_seconds:int,elevation_gain_meters:int,average_pace_seconds_per_km:float,splits:list<array{index:int,distance_meters:int,duration_seconds:int,elevation_meters:int}>,best_efforts:list<array{label:string,distance_meters:int,duration_seconds:int,is_personal_record:bool}>,version:int}
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
            'external_id' => $model->external_id,
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
