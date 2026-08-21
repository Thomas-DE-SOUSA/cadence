<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Read;

use Cadence\Training\Domain\Model\Objective;
use Cadence\Training\Domain\Model\TrainingProgram;
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
