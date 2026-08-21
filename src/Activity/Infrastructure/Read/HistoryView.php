<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Read;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use DateTimeImmutable;

/**
 * Builds the gamified history dashboard: weekly streak, personal-record medals
 * (each effort ranked across all runs), lifetime stats and achievements.
 */
final class HistoryView
{
    private const WEEKDAYS = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];

    /**
     * @param list<ActivityModel> $models most recent first
     *
     * @return array<string, mixed>
     */
    public static function build(array $models, DateTimeImmutable $today): array
    {
        [$records, $rankByActivityDistance] = self::rankEfforts($models);
        $activeDates = self::activeDates($models);

        return [
            'stats' => self::stats($models, $today),
            'streak' => self::streak($activeDates, $today),
            'records' => $records,
            'achievements' => self::achievements($models, $records),
            'activities' => array_map(
                static fn (ActivityModel $m): array => self::activityRow($m, $rankByActivityDistance),
                $models,
            ),
        ];
    }

    /**
     * @param list<ActivityModel> $models
     *
     * @return array{0:list<array<string,mixed>>,1:array<string,int>}
     */
    private static function rankEfforts(array $models): array
    {
        /** @var array<int, list<array{activityId:string,duration:int}>> $byDistance */
        $byDistance = [];
        foreach ($models as $m) {
            foreach (self::efforts($m) as $e) {
                $byDistance[$e['distance']][] = ['activityId' => $m->id, 'duration' => $e['duration']];
            }
        }

        /** @var array<string, int> $rank */
        $rank = [];
        $records = [];
        foreach ($byDistance as $distance => $list) {
            usort($list, static fn (array $a, array $b): int => $a['duration'] <=> $b['duration']);
            foreach ($list as $i => $e) {
                $rank[$e['activityId'].'|'.$distance] = $i + 1;
            }
            $best = $list[0];
            $records[] = [
                'label' => self::distanceLabel($distance),
                'distanceMeters' => $distance,
                'durationSeconds' => $best['duration'],
                'paceSecondsPerKm' => (int) round($best['duration'] / ($distance / 1000)),
                'activityId' => $best['activityId'],
            ];
        }

        usort($records, static fn (array $a, array $b): int => $a['distanceMeters'] <=> $b['distanceMeters']);

        return [$records, $rank];
    }

    /**
     * @param array<string, int> $rankByActivityDistance
     *
     * @return array<string, mixed>
     */
    private static function activityRow(ActivityModel $m, array $rankByActivityDistance): array
    {
        $medals = [];
        foreach (self::efforts($m) as $e) {
            $rank = $rankByActivityDistance[$m->id.'|'.$e['distance']] ?? 99;
            if ($rank <= 3) {
                $medals[$e['distance']] = ['label' => self::distanceLabel($e['distance']), 'rank' => $rank, 'distanceMeters' => $e['distance']];
            }
        }
        $medals = array_values($medals);
        usort($medals, static fn (array $a, array $b): int => [$a['rank'], $a['distanceMeters']] <=> [$b['rank'], $b['distanceMeters']]);

        return [
            'id' => $m->id,
            'occurredAt' => (string) $m->occurred_at,
            'source' => (string) $m->source,
            'distanceMeters' => (int) $m->distance_meters,
            'movingSeconds' => (int) $m->moving_seconds,
            'averagePaceSecondsPerKm' => (float) $m->average_pace_seconds_per_km,
            'elevationGainMeters' => (int) $m->elevation_gain_meters,
            'medals' => $medals,
            'track' => is_array($m->track) ? $m->track : null,
        ];
    }

    /**
     * Best efforts for standard distances, computed from the (reliable) km
     * splits with a sliding window — never slower than the full run. Falls back
     * to the stored best_efforts only when there are no splits.
     *
     * @return list<array{distance:int,duration:int}>
     */
    private static function efforts(ActivityModel $m): array
    {
        /** @var mixed $rawSplits */
        $rawSplits = $m->splits;
        $splits = array_values(array_filter(is_array($rawSplits) ? $rawSplits : [], 'is_array'));
        if ($splits === []) {
            return self::storedEfforts($m);
        }

        $durations = [];
        $distances = [];
        foreach ($splits as $s) {
            $durations[] = (int) ($s['duration_seconds'] ?? 0);
            $distances[] = (int) ($s['distance_meters'] ?? 1000);
        }
        $n = count($durations);

        $efforts = [];
        foreach ([1, 3, 5, 10, 15, 20, 21, 30, 42] as $km) {
            if ($n < $km) {
                continue;
            }
            $bestDuration = null;
            $bestDistance = 0;
            for ($i = 0; $i + $km <= $n; $i++) {
                $windowDuration = 0;
                $windowDistance = 0;
                for ($j = 0; $j < $km; $j++) {
                    $windowDuration += $durations[$i + $j];
                    $windowDistance += $distances[$i + $j];
                }
                if ($windowDuration > 0 && ($bestDuration === null || $windowDuration < $bestDuration)) {
                    $bestDuration = $windowDuration;
                    $bestDistance = $windowDistance;
                }
            }
            if ($bestDuration !== null && $bestDistance > 0) {
                $nominal = $km * 1000;
                $efforts[] = ['distance' => $nominal, 'duration' => (int) round($bestDuration * $nominal / $bestDistance)];
            }
        }

        return $efforts;
    }

    /**
     * @return list<array{distance:int,duration:int}>
     */
    private static function storedEfforts(ActivityModel $m): array
    {
        $efforts = [];
        /** @var mixed $raw */
        $raw = $m->best_efforts;
        foreach (is_array($raw) ? $raw : [] as $e) {
            if (! is_array($e)) {
                continue;
            }
            $distance = (int) ($e['distance_meters'] ?? 0);
            $duration = (int) ($e['duration_seconds'] ?? 0);
            if ($distance > 0 && $duration > 0) {
                $efforts[] = ['distance' => $distance, 'duration' => $duration];
            }
        }

        return $efforts;
    }

    /**
     * @param list<ActivityModel> $models
     *
     * @return array<string, string>
     */
    private static function activeDates(array $models): array
    {
        $dates = [];
        foreach ($models as $m) {
            $dates[substr((string) $m->occurred_at, 0, 10)] = true;
        }

        /** @var array<string, string> $out */
        $out = [];
        foreach (array_keys($dates) as $d) {
            $out[$d] = (new DateTimeImmutable($d))->format('o-W');
        }

        return $out;
    }

    /**
     * @param array<string, string> $activeDates date => ISO year-week
     *
     * @return array{weeks:int,days:list<array{label:string,date:string,active:bool,today:bool}>}
     */
    private static function streak(array $activeDates, DateTimeImmutable $today): array
    {
        $activeWeeks = array_flip(array_values($activeDates));

        $anchor = isset($activeWeeks[$today->format('o-W')]) ? $today : $today->modify('-7 days');
        $weeks = 0;
        $w = $anchor;
        while (isset($activeWeeks[$w->format('o-W')])) {
            $weeks++;
            $w = $w->modify('-7 days');
        }

        $monday = $today->modify('-'.((int) $today->format('N') - 1).' days');
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $monday->modify("+{$i} days");
            $ds = $d->format('Y-m-d');
            $days[] = [
                'label' => self::WEEKDAYS[$i],
                'date' => $ds,
                'active' => isset($activeDates[$ds]),
                'today' => $ds === $today->format('Y-m-d'),
            ];
        }

        return ['weeks' => $weeks, 'days' => $days];
    }

    /**
     * @param list<ActivityModel> $models
     *
     * @return array{totalActivities:int,totalDistanceMeters:int,thisWeekMeters:int,lastActivityDate:string|null}
     */
    private static function stats(array $models, DateTimeImmutable $today): array
    {
        $weekStart = $today->modify('-'.((int) $today->format('N') - 1).' days')->format('Y-m-d');
        $total = 0;
        $thisWeek = 0;
        foreach ($models as $m) {
            $total += (int) $m->distance_meters;
            if (substr((string) $m->occurred_at, 0, 10) >= $weekStart) {
                $thisWeek += (int) $m->distance_meters;
            }
        }

        return [
            'totalActivities' => count($models),
            'totalDistanceMeters' => $total,
            'thisWeekMeters' => $thisWeek,
            'lastActivityDate' => $models === [] ? null : (string) $models[0]->occurred_at,
        ];
    }

    /**
     * @param list<ActivityModel> $models
     * @param list<array<string,mixed>> $records
     *
     * @return list<array{id:string,title:string,description:string,icon:string,unlocked:bool}>
     */
    private static function achievements(array $models, array $records): array
    {
        $maxDistance = 0;
        $totalDistance = 0;
        $minPace = null;
        foreach ($models as $m) {
            $maxDistance = max($maxDistance, (int) $m->distance_meters);
            $totalDistance += (int) $m->distance_meters;
            $pace = (float) $m->average_pace_seconds_per_km;
            if ($pace > 0) {
                $minPace = $minPace === null ? $pace : min($minPace, $pace);
            }
        }
        $count = count($models);

        $best10k = null;
        foreach ($records as $r) {
            if (($r['distanceMeters'] ?? 0) === 10000) {
                $best10k = (int) $r['durationSeconds'];
            }
        }

        $defs = [
            ['id' => 'first', 'title' => 'Première foulée', 'description' => 'Ta première sortie enregistrée', 'icon' => 'footprints', 'unlocked' => $count >= 1],
            ['id' => 'five', 'title' => '5 km bouclés', 'description' => 'Couvrir 5 km sur une sortie', 'icon' => 'route', 'unlocked' => $maxDistance >= 5000],
            ['id' => 'ten', 'title' => '10 km bouclés', 'description' => 'Couvrir 10 km sur une sortie', 'icon' => 'route', 'unlocked' => $maxDistance >= 10000],
            ['id' => 'record', 'title' => 'Sur le podium', 'description' => 'Un record personnel enregistré', 'icon' => 'trophy', 'unlocked' => $records !== []],
            ['id' => 'speed', 'title' => 'Vitesse de croisière', 'description' => 'Une sortie sous 4:30/km', 'icon' => 'gauge', 'unlocked' => $minPace !== null && $minPace <= 270],
            ['id' => 'vol50', 'title' => '50 km au compteur', 'description' => '50 km cumulés', 'icon' => 'mountain', 'unlocked' => $totalDistance >= 50000],
            ['id' => 'vol100', 'title' => '100 km au compteur', 'description' => '100 km cumulés', 'icon' => 'mountain', 'unlocked' => $totalDistance >= 100000],
            ['id' => 'reg10', 'title' => 'Assidu', 'description' => '10 sorties enregistrées', 'icon' => 'calendar', 'unlocked' => $count >= 10],
            ['id' => 'sub40', 'title' => 'Objectif sub-40', 'description' => 'Un 10 km sous 40:00', 'icon' => 'target', 'unlocked' => $best10k !== null && $best10k <= 2400],
        ];

        return $defs;
    }

    private static function distanceLabel(int $meters): string
    {
        return match (true) {
            $meters === 42000 => 'Marathon',
            $meters === 21000 => 'Semi',
            $meters === 3219 => '2 miles',
            $meters === 1609 => '1 mile',
            $meters % 1000 === 0 => intdiv($meters, 1000).' km',
            default => number_format($meters / 1000, 2, ',', ' ').' km',
        };
    }
}
