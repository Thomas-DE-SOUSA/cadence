<?php

declare(strict_types=1);

namespace Cadence\Athlete\Infrastructure\Persistence\Eloquent;

use Cadence\Athlete\Domain\Model\Athlete;
use Cadence\Athlete\Domain\Port\AthleteRepository;
use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Outbox\OutboxEventModel;
use Cadence\Shared\Infrastructure\Persistence\ConcurrencyException;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

final class EloquentAthleteRepository implements AthleteRepository
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function save(Athlete $athlete, array $events): void
    {
        $snapshot = $athlete->toSnapshot();

        try {
            DB::transaction(function () use ($snapshot, $events): void {
                $attributes = [
                    'tenant_id' => $snapshot['tenant_id'],
                    'profile' => $snapshot['profile'],
                    'created_at' => $snapshot['created_at'],
                    'version' => $snapshot['version'],
                ];

                if ($snapshot['version'] === 1) {
                    AthleteModel::query()->create(['id' => $snapshot['id'], ...$attributes]);
                } else {
                    $affected = AthleteModel::query()
                        ->where('id', $snapshot['id'])
                        ->where('tenant_id', $snapshot['tenant_id'])
                        ->where('version', $snapshot['version'] - 1)
                        ->update($attributes);

                    if ($affected === 0) {
                        throw new ConcurrencyException("Athlete profile {$snapshot['id']} was modified concurrently.");
                    }
                }

                $ordinal = 0;
                foreach ($events as $event) {
                    OutboxEventModel::query()->create([
                        'id' => (string) Str::orderedUuid(),
                        'aggregate_id' => $event->aggregateId,
                        'aggregate_type' => 'athlete',
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
            $this->logger->error('Failed to persist athlete profile', [
                'aggregate_id' => $snapshot['id'],
                'tenant_id' => $snapshot['tenant_id'],
                'exception' => $e->getMessage(),
            ]);

            throw new PersistenceFailure('Could not persist the athlete profile.', 0, $e);
        }
    }

    public function ofTenant(TenantId $tenant): ?Athlete
    {
        $model = AthleteModel::query()
            ->where('tenant_id', $tenant->value)
            ->first();

        if (! $model instanceof AthleteModel) {
            return null;
        }

        /** @var array<string, mixed> $profile */
        $profile = $model->profile;

        /** @var array{id:string,tenant_id:string,profile:array<string,mixed>,created_at:string,version:int} $snapshot */
        $snapshot = [
            'id' => $model->id,
            'tenant_id' => $model->tenant_id,
            'profile' => $profile,
            'created_at' => (string) $model->created_at,
            'version' => (int) $model->version,
        ];

        // @phpstan-ignore-next-line argument.type — JSON column is trusted to hold the profile snapshot shape.
        return Athlete::fromSnapshot($snapshot);
    }
}
