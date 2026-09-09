<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\ScheduleWorkout;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Strength\Domain\Enum\WorkoutStatus;
use Cadence\Strength\Domain\Exception\TemplateNotFound;
use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Port\WorkoutTemplateRepository;

/**
 * Places a template on an agenda day: creates a PLANNED session pre-filled,
 * per exercise, with the weights performed on the athlete's latest training
 * date for that exercise (progressive-overload memory) — falling back to the
 * template's target sets for exercises never performed. Only the plan-shaped
 * fields carry over (load / reps / duration / warm-up structure); per-session
 * performance data (RPE, done state) is not copied into a plan.
 */
final readonly class ScheduleWorkoutUseCase
{
    public function __construct(
        private WorkoutTemplateRepository $templates,
        private StrengthSessionRepository $sessions,
        private IdGenerator $ids,
    ) {
    }

    public function execute(ScheduleWorkoutInput $input, ExecutionContext $context): string
    {
        $template = $this->templates->ofId($input->templateId, $context->tenant);
        if ($template === null) {
            throw TemplateNotFound::withId($input->templateId);
        }

        $snap = $template->toSnapshot();
        $lastPerformed = $this->lastPerformedSets($context->tenant);
        $id = $this->ids->generate();

        $exercises = [];
        foreach ($snap['exercises'] as $exercise) {
            if (is_array($exercise) && isset($exercise['exercise_id'])) {
                $exerciseId = (string) $exercise['exercise_id'];
                if (isset($lastPerformed[$exerciseId])) {
                    // Carry forward the last performed sets so progression sticks.
                    $exercise['sets'] = $lastPerformed[$exerciseId];
                }
            }
            $exercises[] = $exercise;
        }

        $session = StrengthSession::fromSnapshot([
            'id' => $id,
            'tenant_id' => $context->tenant->value,
            'date' => $input->date,
            'title' => $snap['name'],
            'note' => '',
            'duration_seconds' => null,
            'status' => WorkoutStatus::PLANNED->value,
            'template_id' => $template->id(),
            'version' => 1,
            'exercises' => $exercises,
        ]);

        $this->sessions->save($session);

        return $id;
    }

    /**
     * The sets performed on the latest training date each exercise was done
     * (DONE sessions only; the repo orders by session_date desc). Sibling of
     * {@see \Cadence\Strength\Infrastructure\Read\StrengthView::lastByExercise},
     * which serves the live editor's "last time" reference.
     *
     * @return array<string, list<array<string, mixed>>> exercise_id → its last performed sets, plan-shaped
     */
    private function lastPerformedSets(TenantId $tenant): array
    {
        $last = [];
        // A generous bound so an exercise's memory isn't silently lost past a
        // small page — a single athlete's whole history fits comfortably.
        foreach ($this->sessions->forTenant($tenant, 500) as $session) {
            if (! $session->status()->isDone()) {
                continue;
            }
            foreach ($session->toSnapshot()['exercises'] as $exercise) {
                if (! is_array($exercise) || ! isset($exercise['exercise_id'])) {
                    continue;
                }
                $exerciseId = (string) $exercise['exercise_id'];
                if (isset($last[$exerciseId]) || ! isset($exercise['sets']) || ! is_array($exercise['sets'])) {
                    continue;
                }
                $planSets = $this->toPlanSets($exercise['sets']);
                if ($planSets !== []) {
                    $last[$exerciseId] = $planSets;
                }
            }
        }

        return $last;
    }

    /**
     * Projects performed sets to plan-shaped targets: keeps load / reps /
     * duration / warm-up structure, drops per-session performance (RPE, done —
     * their VO defaults apply). Returns [] when there was no working set last
     * time, so the template target is kept instead.
     *
     * @param array<int|string, mixed> $sets
     *
     * @return list<array<string, mixed>>
     */
    private function toPlanSets(array $sets): array
    {
        $out = [];
        $hasWorkingSet = false;
        foreach ($sets as $set) {
            if (! is_array($set)) {
                continue;
            }
            $isWarmup = (bool) ($set['is_warmup'] ?? false);
            if (! $isWarmup && ($set['done'] ?? true) !== false) {
                $hasWorkingSet = true;
            }
            $out[] = [
                'weight_kg' => $set['weight_kg'] ?? null,
                'reps' => $set['reps'] ?? null,
                'duration_seconds' => $set['duration_seconds'] ?? null,
                'is_warmup' => $isWarmup,
            ];
        }

        return $hasWorkingSet ? $out : [];
    }
}
