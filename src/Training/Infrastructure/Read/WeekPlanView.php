<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Read;

use Cadence\Training\Infrastructure\Persistence\Eloquent\CycleModel;

/**
 * Read helper exposing the planned session type for each day of a date range,
 * so other contexts (e.g. the dashboard) can tell a planned rest day apart from
 * a missed one without reaching into the Training aggregates.
 */
final class WeekPlanView
{
    /**
     * @return array<string, string> date (Y-m-d) => SessionType value (e.g. "REST", "EASY")
     */
    public static function typesByDate(string $tenantId, string $from, string $to): array
    {
        $cycles = CycleModel::query()
            ->where('tenant_id', $tenantId)
            ->get();

        /** @var array<string, string> $out */
        $out = [];
        foreach ($cycles as $cycle) {
            /** @var mixed $sessions */
            $sessions = $cycle->sessions;
            foreach (is_array($sessions) ? $sessions : [] as $session) {
                if (! is_array($session)) {
                    continue;
                }
                $date = substr((string) ($session['date'] ?? ''), 0, 10);
                $type = isset($session['type']) ? (string) $session['type'] : '';
                if ($type !== '' && $date >= $from && $date <= $to) {
                    $out[$date] = $type;
                }
            }
        }

        return $out;
    }
}
