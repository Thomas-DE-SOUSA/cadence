<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Cadence\Strength\Domain\Enum\WeighMoment;
use Cadence\Strength\Domain\Port\WeightEntryRepository;
use Cadence\Strength\Domain\ValueObject\WeightEntry;
use Throwable;

final class EloquentWeightEntryRepository implements WeightEntryRepository
{
    public function __construct(private readonly IdGenerator $ids)
    {
    }

    public function save(TenantId $tenant, WeightEntry $entry): void
    {
        try {
            // One reading per moment per day: overwrite the same row on re-entry.
            $model = WeightEntryModel::query()
                ->firstOrNew([
                    'tenant_id' => $tenant->value,
                    'logged_date' => $entry->date,
                    'moment' => $entry->moment->value,
                ]);

            if (! $model->exists) {
                $model->id = $this->ids->generate();
            }

            $model->fill([
                'weight_kg' => $entry->weightKg,
                'note' => $entry->note,
            ])->save();
        } catch (Throwable $e) {
            throw new PersistenceFailure('Could not persist the weight entry.', 0, $e);
        }
    }

    public function since(TenantId $tenant, string $since): array
    {
        $models = WeightEntryModel::query()
            ->where('tenant_id', $tenant->value)
            ->where('logged_date', '>=', $since)
            ->orderByDesc('logged_date')
            ->get();

        return array_values($models->map(fn (WeightEntryModel $m): WeightEntry => $this->toDomain($m))->all());
    }

    public function latestFor(TenantId $tenant): ?WeightEntry
    {
        $model = WeightEntryModel::query()
            ->where('tenant_id', $tenant->value)
            ->orderByDesc('logged_date')
            ->first();

        return $model instanceof WeightEntryModel ? $this->toDomain($model) : null;
    }

    private function toDomain(WeightEntryModel $m): WeightEntry
    {
        return new WeightEntry(
            $m->logged_date,
            WeighMoment::from($m->moment),
            $m->weight_kg,
            $m->note,
        );
    }
}
