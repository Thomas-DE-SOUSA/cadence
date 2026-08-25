<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Read;

use Cadence\Training\Domain\Model\Cycle;
use Cadence\Training\Domain\Model\Objective;
use Cadence\Training\Domain\Model\TrainingProgram;
use Cadence\Training\Domain\Plan\PlanPhase;
use Cadence\Training\Domain\Plan\TrainingPlan;
use Cadence\Training\Domain\Plan\TrainingPlanCatalog;
use Cadence\Training\Domain\Service\ObjectiveEvaluator;
use Cadence\Training\Domain\ValueObject\ActivitySummary;
use Cadence\Training\Domain\ValueObject\ObjectiveResult;

/** Builds Inertia view arrays for programs, running the coach evaluation. */
final class ProgramView
{
    /**
     * @param list<ActivitySummary> $summaries
     *
     * @return array<string, mixed>
     */
    public static function listItem(TrainingProgram $program, array $summaries): array
    {
        $s = $program->toSnapshot();
        $results = self::evaluate($program, $summaries);
        $achieved = count(array_filter($results, static fn (ObjectiveResult $r): bool => $r->achieved));

        return [
            'id' => $s['id'],
            'name' => $s['name'],
            'targetRaceName' => $s['target_race_name'],
            'targetRaceDate' => $s['target_race_date'],
            'priority' => $s['priority'],
            'status' => $s['status'],
            'objectivesCount' => count($results),
            'achievedCount' => $achieved,
            'assignedCount' => count($s['assigned_activity_ids']),
        ];
    }

    /**
     * @param list<ActivitySummary> $summaries
     *
     * @return array<string, mixed>
     */
    public static function detail(TrainingProgram $program, array $summaries): array
    {
        $s = $program->toSnapshot();
        $results = self::evaluate($program, $summaries);

        return [
            'id' => $s['id'],
            'name' => $s['name'],
            'goal' => $s['goal'],
            'targetRaceName' => $s['target_race_name'],
            'targetRaceDate' => $s['target_race_date'],
            'startDate' => $s['start_date'],
            'endDate' => $s['end_date'],
            'priority' => $s['priority'],
            'status' => $s['status'],
            'objectives' => array_map(static fn (ObjectiveResult $r): array => [
                'id' => $r->objectiveId,
                'label' => $r->label,
                'type' => $r->type,
                'achieved' => $r->achieved,
                'progress' => round($r->progress, 3),
                'detail' => $r->detail,
            ], $results),
            'activities' => array_map(static fn (ActivitySummary $a): array => [
                'id' => $a->activityId,
                'occurredAt' => $a->occurredAtIso,
                'distanceMeters' => $a->distanceMeters,
                'movingSeconds' => $a->movingSeconds,
                'averagePaceSecondsPerKm' => $a->averagePaceSecondsPerKm,
            ], $summaries),
        ];
    }

    /**
     * Maps cycles to the view, grouping planned sessions into 7-day weeks from
     * the cycle start. Training slots fill flexibly: any run logged within a
     * week auto-fills that week's open (non-rest) slots in order — the weekday
     * is irrelevant, matching an athlete who trains on no fixed day. A manual
     * link pins a run to a slot and takes precedence.
     *
     * @param list<Cycle> $cycles
     * @param array<string, array<string, mixed>> $activityLookup keyed by activity id
     *
     * @return list<array<string, mixed>>
     */
    public static function cycles(array $cycles, array $activityLookup = []): array
    {
        // Bucket every run by day for fast per-week lookup.
        /** @var array<string, list<array<string, mixed>>> $runsByDate */
        $runsByDate = [];
        foreach ($activityLookup as $stats) {
            $day = substr((string) ($stats['occurredAt'] ?? ''), 0, 10);
            if ($day !== '') {
                $runsByDate[$day][] = $stats;
            }
        }

        return array_map(static function (Cycle $cycle) use ($activityLookup, $runsByDate): array {
            $s = $cycle->toSnapshot();
            $start = new \DateTimeImmutable($s['start_date']);

            // Runs the athlete manually pinned to a slot anywhere in this cycle
            // are removed from the auto-fill pool.
            /** @var array<string, true> $manualIds */
            $manualIds = [];
            foreach ($s['sessions'] as $session) {
                if (($session['activity_id'] ?? null) !== null) {
                    $manualIds[(string) $session['activity_id']] = true;
                }
            }

            /** @var array<int, list<array<string, mixed>>> $weeks */
            $weeks = [];
            foreach ($s['sessions'] as $session) {
                $days = (int) $start->diff(new \DateTimeImmutable($session['date']))->days;
                $weeks[intdiv($days, 7)][] = $session;
            }
            ksort($weeks);

            $grouped = [];
            $index = 1;
            foreach ($weeks as $weekIndex => $weekSessions) {
                $weekStart = $start->modify('+'.($weekIndex * 7).' days');

                // Pool of runs done during this week, earliest first, minus the
                // ones already pinned by hand.
                $pool = [];
                for ($d = 0; $d < 7; $d++) {
                    $day = $weekStart->modify("+{$d} days")->format('Y-m-d');
                    foreach ($runsByDate[$day] ?? [] as $stats) {
                        if (! isset($manualIds[(string) $stats['id']])) {
                            $pool[] = $stats;
                        }
                    }
                }

                $poolIndex = 0;
                $done = 0;
                $total = 0;
                $sessions = [];
                foreach ($weekSessions as $session) {
                    $isRest = $session['type'] === 'REST';
                    $manualId = $session['activity_id'] ?? null;
                    $manual = $manualId !== null ? ($activityLookup[$manualId] ?? null) : null;

                    $auto = null;
                    if (! $isRest && $manual === null && isset($pool[$poolIndex])) {
                        $auto = $pool[$poolIndex];
                        $poolIndex++;
                    }
                    $actual = $manual ?? $auto;

                    if (! $isRest) {
                        $total++;
                        if ($actual !== null) {
                            $done++;
                        }
                    }

                    $sessions[] = [
                        'date' => $session['date'],
                        'suggestedDate' => $session['suggested_date'] ?? null,
                        'type' => $session['type'],
                        'title' => $session['title'],
                        'description' => $session['description'],
                        'targetDistanceMeters' => $session['target_distance_meters'],
                        'targetDurationSeconds' => $session['target_duration_seconds'],
                        'targetPaceSecondsPerKm' => $session['target_pace_seconds_per_km'],
                        'steps' => array_map(static fn (array $st): array => [
                            'label' => $st['label'],
                            'repeat' => $st['repeat'],
                            'distanceMeters' => $st['distance_meters'],
                            'durationSeconds' => $st['duration_seconds'],
                            'paceSecondsPerKm' => $st['pace_seconds_per_km'],
                            'recoverySeconds' => $st['recovery_seconds'],
                            'note' => $st['note'],
                        ], $session['steps']),
                        'actual' => $actual,
                        'manual' => $manual !== null,
                    ];
                }

                $grouped[] = [
                    'label' => 'Semaine '.$index,
                    'startDate' => $weekStart->format('Y-m-d'),
                    'done' => $done,
                    'total' => $total,
                    'sessions' => $sessions,
                ];
                $index++;
            }

            return [
                'id' => $s['id'],
                'name' => $s['name'],
                'focus' => $s['focus'],
                'startDate' => $s['start_date'],
                'endDate' => $s['end_date'],
                'phaseIndex' => $s['phase_index'],
                'status' => $s['status'],
                'weeks' => $grouped,
            ];
        }, $cycles);
    }

    /**
     * The full roadmap of a program's plan: every phase with its status, so the
     * UI can show the current cycle as active and the rest as locked/done.
     *
     * @param list<Cycle> $cycles
     *
     * @return list<array<string, mixed>>
     */
    public static function roadmap(?string $planKey, array $cycles): array
    {
        $plan = $planKey !== null ? TrainingPlanCatalog::byKey($planKey) : null;
        if ($plan === null) {
            return [];
        }

        $completed = 0;
        foreach ($cycles as $c) {
            if ($c->isCompleted()) {
                $completed++;
            }
        }
        $activeIndex = count($cycles) - 1;

        $roadmap = [];
        foreach ($plan->phases as $index => $phase) {
            $materialised = $index <= $activeIndex;
            $status = 'locked';
            if ($materialised) {
                $status = ($cycles[$index] ?? null)?->isCompleted() === true ? 'done' : 'active';
            }

            $roadmap[] = [
                'index' => $index,
                'name' => $phase->name,
                'focus' => $phase->focus,
                'weeks' => $phase->weeks,
                'status' => $status,
                'cycleId' => $materialised ? $cycles[$index]->id()->value : null,
            ];
        }

        return $roadmap;
    }

    /** @return list<array<string, mixed>> */
    public static function plans(): array
    {
        return array_map(static fn (TrainingPlan $plan): array => [
            'key' => $plan->key,
            'name' => $plan->name,
            'summary' => $plan->summary,
            'goal' => $plan->goal,
            'targetRaceName' => $plan->targetRaceName,
            'daysPerWeek' => $plan->daysPerWeek,
            'totalWeeks' => $plan->totalWeeks(),
            'phases' => array_map(static fn (PlanPhase $p): array => [
                'name' => $p->name,
                'focus' => $p->focus,
                'weeks' => $p->weeks,
            ], $plan->phases),
        ], TrainingPlanCatalog::all());
    }

    /**
     * @param list<ActivitySummary> $summaries
     *
     * @return list<ObjectiveResult>
     */
    private static function evaluate(TrainingProgram $program, array $summaries): array
    {
        return array_map(
            static fn (Objective $o): ObjectiveResult => ObjectiveEvaluator::evaluate($o, $summaries),
            $program->objectives(),
        );
    }
}
