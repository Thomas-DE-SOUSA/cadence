<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Persistence\Eloquent;

use Cadence\Coaching\Domain\Port\WellnessCheckInRepository;
use Cadence\Coaching\Domain\ValueObject\WellnessCheckIn;
use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Throwable;

final class EloquentWellnessCheckInRepository implements WellnessCheckInRepository
{
    public function __construct(private readonly IdGenerator $ids)
    {
    }

    public function save(TenantId $tenant, WellnessCheckIn $checkIn): void
    {
        try {
            // One check-in per day: keep the same row (and id) when re-submitting today.
            $model = WellnessCheckInModel::query()
                ->firstOrNew(['tenant_id' => $tenant->value, 'check_date' => $checkIn->date]);

            if (! $model->exists) {
                $model->id = $this->ids->generate();
            }

            $model->fill([
                'sleep' => $checkIn->sleep,
                'energy' => $checkIn->energy,
                'legs' => $checkIn->legs,
                'motivation' => $checkIn->motivation,
                'pain_level' => $checkIn->painLevel,
                'pain_location' => $checkIn->painLocation,
                'note' => $checkIn->note,
            ])->save();
        } catch (Throwable $e) {
            throw new PersistenceFailure('Could not persist the wellness check-in.', 0, $e);
        }
    }

    public function forDate(TenantId $tenant, string $date): ?WellnessCheckIn
    {
        $model = WellnessCheckInModel::query()
            ->where('tenant_id', $tenant->value)
            ->where('check_date', $date)
            ->first();

        return $model instanceof WellnessCheckInModel ? $this->toDomain($model) : null;
    }

    public function latestFor(TenantId $tenant): ?WellnessCheckIn
    {
        $model = WellnessCheckInModel::query()
            ->where('tenant_id', $tenant->value)
            ->orderByDesc('check_date')
            ->first();

        return $model instanceof WellnessCheckInModel ? $this->toDomain($model) : null;
    }

    public function since(TenantId $tenant, string $since): array
    {
        $models = WellnessCheckInModel::query()
            ->where('tenant_id', $tenant->value)
            ->where('check_date', '>=', $since)
            ->orderByDesc('check_date')
            ->get();

        return array_values($models->map(fn (WellnessCheckInModel $m): WellnessCheckIn => $this->toDomain($m))->all());
    }

    private function toDomain(WellnessCheckInModel $m): WellnessCheckIn
    {
        return new WellnessCheckIn(
            $m->check_date,
            $m->sleep,
            $m->energy,
            $m->legs,
            $m->motivation,
            $m->pain_level,
            $m->pain_location,
            $m->note,
        );
    }
}
