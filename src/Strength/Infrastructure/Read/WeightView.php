<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Read;

use Cadence\Strength\Domain\ValueObject\WeightEntry;
use DateTimeImmutable;

/** Shapes body-weight readings for the Poids page: weekly averages + recent list. */
final class WeightView
{
    /**
     * Average weight per Mon–Sun week (morning + evening readings pooled),
     * oldest week first. Only weeks that actually have readings appear;
     * `count` says how many readings back each average.
     *
     * @param list<WeightEntry> $entries
     *
     * @return list<array{weekStart:string,avgKg:float,count:int}>
     */
    public static function weeklyAverages(array $entries): array
    {
        /** @var array<string, array{sum:float,count:int}> $weeks */
        $weeks = [];

        foreach ($entries as $e) {
            $monday = (new DateTimeImmutable($e->date))->modify('monday this week')->format('Y-m-d');
            $weeks[$monday] ??= ['sum' => 0.0, 'count' => 0];
            $weeks[$monday]['sum'] += $e->weightKg;
            $weeks[$monday]['count']++;
        }

        ksort($weeks);

        $out = [];
        foreach ($weeks as $monday => $agg) {
            $out[] = [
                'weekStart' => $monday,
                'avgKg' => round($agg['sum'] / $agg['count'], 1),
                'count' => $agg['count'],
            ];
        }

        return $out;
    }

    /**
     * The most recent readings (already newest-first from the repo), flattened
     * for the raw log list.
     *
     * @param list<WeightEntry> $entries
     *
     * @return list<array{date:string,moment:string,momentLabel:string,weightKg:float}>
     */
    public static function recent(array $entries, int $limit = 14): array
    {
        $out = [];
        foreach (array_slice($entries, 0, $limit) as $e) {
            $out[] = [
                'date' => $e->date,
                'moment' => $e->moment->value,
                'momentLabel' => $e->moment->label(),
                'weightKg' => $e->weightKg,
            ];
        }

        return $out;
    }
}
