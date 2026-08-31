<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Cadence\Strength\Domain\Model\MuscuProfile;
use Cadence\Strength\Domain\Port\MuscuProfileRepository;
use Throwable;

final class EloquentMuscuProfileRepository implements MuscuProfileRepository
{
    public function __construct(private readonly IdGenerator $ids)
    {
    }

    public function save(MuscuProfile $profile): void
    {
        $s = $profile->toSnapshot();

        try {
            $model = MuscuProfileModel::query()->firstOrNew(['tenant_id' => $s['tenant_id']]);
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
            throw new PersistenceFailure('Could not persist the muscu profile.', 0, $e);
        }
    }

    public function forTenant(TenantId $tenant): ?MuscuProfile
    {
        $model = MuscuProfileModel::query()->where('tenant_id', $tenant->value)->first();

        return $model instanceof MuscuProfileModel ? MuscuProfile::fromSnapshot([
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
