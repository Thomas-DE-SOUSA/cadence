<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Cadence\Strength\Domain\Enum\Meal;
use Cadence\Strength\Domain\Port\NutritionEntryRepository;
use Cadence\Strength\Domain\ValueObject\NutritionEntry;
use Throwable;

final class EloquentNutritionEntryRepository implements NutritionEntryRepository
{
    public function save(TenantId $tenant, NutritionEntry $entry): void
    {
        try {
            NutritionEntryModel::query()->updateOrCreate(['id' => $entry->id], [
                'tenant_id' => $tenant->value,
                'logged_date' => $entry->date,
                'meal' => $entry->meal->value,
                'description' => $entry->description,
                'kcal' => $entry->kcal,
                'protein_g' => $entry->proteinG,
                'fat_g' => $entry->fatG,
                'carbs_g' => $entry->carbsG,
            ]);
        } catch (Throwable $e) {
            throw new PersistenceFailure('Could not persist the nutrition entry.', 0, $e);
        }
    }

    public function forDate(TenantId $tenant, string $date): array
    {
        $models = NutritionEntryModel::query()
            ->where('tenant_id', $tenant->value)
            ->where('logged_date', $date)
            ->orderBy('created_at')
            ->get();

        return array_values($models->map(fn (NutritionEntryModel $m): NutritionEntry => $this->toDomain($m))->all());
    }

    public function delete(TenantId $tenant, string $id): void
    {
        NutritionEntryModel::query()
            ->where('tenant_id', $tenant->value)
            ->where('id', $id)
            ->delete();
    }

    private function toDomain(NutritionEntryModel $m): NutritionEntry
    {
        return new NutritionEntry(
            $m->id,
            $m->logged_date,
            Meal::from($m->meal),
            $m->description,
            $m->kcal,
            $m->protein_g,
            $m->fat_g,
            $m->carbs_g,
        );
    }
}
