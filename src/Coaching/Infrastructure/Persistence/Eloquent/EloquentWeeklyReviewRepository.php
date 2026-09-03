<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Persistence\Eloquent;

use Cadence\Coaching\Domain\Model\WeeklyReview;
use Cadence\Coaching\Domain\Port\WeeklyReviewRepository;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewId;
use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Infrastructure\Persistence\PersistenceFailure;
use Throwable;

final class EloquentWeeklyReviewRepository implements WeeklyReviewRepository
{
    public function save(WeeklyReview $review): void
    {
        $s = $review->toSnapshot();

        try {
            WeeklyReviewModel::query()->updateOrCreate(['id' => $s['id']], [
                'tenant_id' => $s['tenant_id'],
                'week_start' => $s['week_start'],
                'messages' => $s['messages'],
                'version' => $s['version'],
            ]);
        } catch (Throwable $e) {
            throw new PersistenceFailure('Could not persist the weekly review.', 0, $e);
        }
    }

    public function ofId(WeeklyReviewId $id, TenantId $tenant): ?WeeklyReview
    {
        $model = WeeklyReviewModel::query()
            ->where('id', $id->value)
            ->where('tenant_id', $tenant->value)
            ->first();

        return $model instanceof WeeklyReviewModel ? WeeklyReview::fromSnapshot($this->toSnapshot($model)) : null;
    }

    public function forWeek(string $weekStart, TenantId $tenant): ?WeeklyReview
    {
        $model = WeeklyReviewModel::query()
            ->where('week_start', $weekStart)
            ->where('tenant_id', $tenant->value)
            ->first();

        return $model instanceof WeeklyReviewModel ? WeeklyReview::fromSnapshot($this->toSnapshot($model)) : null;
    }

    /**
     * Normalises the stored JSON into the aggregate's typed snapshot. Mirrors
     * the message shaping of {@see EloquentConversationRepository}.
     *
     * @return array{id:string,tenant_id:string,week_start:string,messages:list<array{id:string,role:string,text:string,occurred_at:string,proposal:array{date:string,type:string,title:string,description:string,target_distance_meters:int|null,target_duration_seconds:int|null,target_pace_seconds_per_km:int|null,rationale:string}|null,proposal_applied:bool}>,version:int}
     */
    private function toSnapshot(WeeklyReviewModel $model): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $model->messages;

        return [
            'id' => $model->id,
            'tenant_id' => $model->tenant_id,
            'week_start' => $model->week_start,
            'messages' => array_map(static function (array $m): array {
                /** @var array<string, mixed>|null $p */
                $p = is_array($m['proposal'] ?? null) ? $m['proposal'] : null;

                return [
                    'id' => (string) $m['id'],
                    'role' => (string) $m['role'],
                    'text' => (string) $m['text'],
                    'occurred_at' => (string) $m['occurred_at'],
                    'proposal' => $p === null ? null : [
                        'date' => (string) $p['date'],
                        'type' => (string) $p['type'],
                        'title' => (string) $p['title'],
                        'description' => (string) $p['description'],
                        'target_distance_meters' => isset($p['target_distance_meters']) ? (int) $p['target_distance_meters'] : null,
                        'target_duration_seconds' => isset($p['target_duration_seconds']) ? (int) $p['target_duration_seconds'] : null,
                        'target_pace_seconds_per_km' => isset($p['target_pace_seconds_per_km']) ? (int) $p['target_pace_seconds_per_km'] : null,
                        'rationale' => (string) $p['rationale'],
                    ],
                    'proposal_applied' => (bool) ($m['proposal_applied'] ?? false),
                ];
            }, $rows),
            'version' => (int) $model->version,
        ];
    }
}
