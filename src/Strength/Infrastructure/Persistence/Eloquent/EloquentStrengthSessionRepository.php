<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Persistence\Eloquent;

use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Throwable;

final class EloquentStrengthSessionRepository implements StrengthSessionRepository
{
    public function save(StrengthSession $session): void
    {
        $s = $session->toSnapshot();

        try {
            StrengthSessionModel::query()->updateOrCreate(['id' => $s['id']], [
                'tenant_id' => $s['tenant_id'],
                'session_date' => $s['date'],
                'title' => $s['title'],
                'note' => $s['note'],
                'duration_seconds' => $s['duration_seconds'],
                'status' => $s['status'],
                'template_id' => $s['template_id'],
                'version' => $s['version'],
                'exercises' => $s['exercises'],
            ]);
        } catch (Throwable $e) {
            throw new PersistenceFailure('Could not persist the strength session.', 0, $e);
        }
    }

    public function ofId(string $id, TenantId $tenant): ?StrengthSession
    {
        $model = StrengthSessionModel::query()
            ->where('id', $id)
            ->where('tenant_id', $tenant->value)
            ->first();

        return $model instanceof StrengthSessionModel ? $this->toDomain($model) : null;
    }

    public function delete(string $id, TenantId $tenant): void
    {
        StrengthSessionModel::query()->where('id', $id)->where('tenant_id', $tenant->value)->delete();
    }

    public function forTenant(TenantId $tenant, int $limit = 50): array
    {
        $models = StrengthSessionModel::query()
            ->where('tenant_id', $tenant->value)
            ->orderByDesc('session_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return array_values($models->map(fn (StrengthSessionModel $m): StrengthSession => $this->toDomain($m))->all());
    }

    public function forRange(TenantId $tenant, string $from, string $to): array
    {
        $models = StrengthSessionModel::query()
            ->where('tenant_id', $tenant->value)
            ->whereBetween('session_date', [$from, $to])
            ->orderBy('session_date')
            ->get();

        return array_values($models->map(fn (StrengthSessionModel $m): StrengthSession => $this->toDomain($m))->all());
    }

    private function toDomain(StrengthSessionModel $m): StrengthSession
    {
        return StrengthSession::fromSnapshot([
            'id' => $m->id,
            'tenant_id' => $m->tenant_id,
            'date' => $m->session_date,
            'title' => $m->title,
            'note' => $m->note,
            'duration_seconds' => $m->duration_seconds,
            'status' => $m->status,
            'template_id' => $m->template_id,
            'version' => $m->version,
            'exercises' => $m->exercises,
        ]);
    }
}
