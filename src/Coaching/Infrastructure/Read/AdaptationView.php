<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Read;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Coaching\Domain\Service\AdaptationAnalyzer;
use Cadence\Training\Infrastructure\Persistence\Eloquent\CycleModel;

/** Builds the weekly adaptation recommendation from the current cycle + load. */
final class AdaptationView
{
    /**
     * @param array<string, mixed> $load the Forme payload (acwr, form, zones)
     *
     * @return array<string, mixed>|null
     */
    public static function build(string $tenantId, array $load, AdaptationAnalyzer $analyzer): ?array
    {
        $cycle = CycleModel::query()->where('tenant_id', $tenantId)->orderByDesc('start_date')->first();
        if ($cycle === null) {
            return null;
        }

        $sessions = $cycle->sessions;
        $planned = 0;
        foreach ($sessions as $s) {
            if (is_array($s) && ($s['type'] ?? '') !== 'REST') {
                $planned++;
            }
        }
        if ($planned === 0) {
            return null;
        }

        $from = substr((string) $cycle->start_date, 0, 10);
        $to = substr((string) $cycle->end_date, 0, 10);
        $runs = ActivityModel::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->count();
        $done = min($runs, $planned);

        /** @var array{easy?:int,total?:int} $zones */
        $zones = is_array($load['zones'] ?? null) ? $load['zones'] : [];
        $total = (int) ($zones['total'] ?? 0);
        $easyPct = $total > 0 ? (int) round(100 * (int) ($zones['easy'] ?? 0) / $total) : 0;

        $report = $analyzer->analyze(
            $done,
            $planned,
            (float) ($load['acwr'] ?? 0),
            (int) ($load['form'] ?? 0),
            $easyPct,
        );

        return [
            ...$report->toArray(),
            'done' => $done,
            'planned' => $planned,
            'cycleName' => (string) $cycle->name,
        ];
    }
}
