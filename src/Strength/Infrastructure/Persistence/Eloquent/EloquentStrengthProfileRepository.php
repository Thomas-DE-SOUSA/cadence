<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Cadence\Strength\Domain\Model\StrengthProfile;
use Cadence\Strength\Domain\Port\StrengthProfileRepository;
use Throwable;

final class EloquentStrengthProfileRepository implements StrengthProfileRepository
{
    public function __construct(private readonly IdGenerator $ids)
    {
    }

    public function save(StrengthProfile $profile): void
    {
        $s = $profile->toSnapshot();

        try {
            $model = StrengthProfileModel::query()->firstOrNew(['tenant_id' => $s['tenant_id']]);
            if (! $model->exists) {
                $model->id = $this->ids->generate();
            }
            $model->fill([
                'goal' => $s['goal'],
                'level' => $s['level'],
                'bodyweight_kg' => $s['bodyweight_kg'],
                'weekly_frequency' => $s['weekly_frequency'],
                'split' => $s['split'],
                'equipment' => $s['equipment'],
                'priorities' => $s['priorities'],
                'limitations' => $s['limitations'],
                'note' => $s['note'],
            ])->save();
        } catch (Throwable $e) {
            throw new PersistenceFailure('Could not persist the strength profile.', 0, $e);
        }
    }

    public function forTenant(TenantId $tenant): ?StrengthProfile
    {
        $model = StrengthProfileModel::query()->where('tenant_id', $tenant->value)->first();

        return $model instanceof StrengthProfileModel ? StrengthProfile::fromSnapshot([
            'tenant_id' => $model->tenant_id,
            'goal' => $model->goal,
            'level' => $model->level,
            'bodyweight_kg' => $model->bodyweight_kg,
            'weekly_frequency' => $model->weekly_frequency,
            'split' => $model->split,
            'equipment' => $model->equipment,
            'priorities' => $model->priorities,
            'limitations' => $model->limitations,
            'note' => $model->note,
        ]) : null;
    }
}
