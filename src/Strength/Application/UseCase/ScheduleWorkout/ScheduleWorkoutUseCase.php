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
 * Places a template on an agenda day: creates a PLANNED session whose structure
 * comes from the template (its full set count and warm-ups), with each working
 * set pre-filled from the loads the athlete last performed for that exercise
 * (progressive-overload memory) — falling back to the template target for
 * exercises never performed. Keeping the template's structure means a session
 * re-scheduled after a partial day still offers every planned set (e.g. 0/3),
 * even though the completed log only kept the sets that were actually done.
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
        $lastPerformed = $this->lastPerformedWorkingSets($context->tenant);
        $id = $this->ids->generate();

        $exercises = [];
        foreach ($snap['exercises'] as $exercise) {
            if (is_array($exercise) && isset($exercise['exercise_id']) && isset($exercise['sets']) && is_array($exercise['sets'])) {
                $exerciseId = (string) $exercise['exercise_id'];
                if (isset($lastPerformed[$exerciseId])) {
                    // Keep the template's set structure; overlay the achieved loads.
                    $exercise['sets'] = $this->applyOverload($exercise['sets'], $lastPerformed[$exerciseId]);
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
     * The working-set loads performed on the latest training date each exercise
     * was done (DONE sessions only; the repo orders by session_date desc).
     * Warm-ups are excluded — the template owns the warm-up structure. Sibling of
     * {@see \Cadence\Strength\Infrastructure\Read\StrengthView::lastByExercise},
     * which serves the live editor's "last time" reference.
     *
     * @return array<string, list<array{weight_kg:mixed,reps:mixed,duration_seconds:mixed}>> exercise_id → its last performed working loads, in order
     */
    private function lastPerformedWorkingSets(TenantId $tenant): array
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
                $working = [];
                foreach ($exercise['sets'] as $set) {
                    if (! is_array($set) || ($set['is_warmup'] ?? false) || ($set['done'] ?? true) === false) {
                        continue;
                    }
                    $working[] = [
                        'weight_kg' => $set['weight_kg'] ?? null,
                        'reps' => $set['reps'] ?? null,
                        'duration_seconds' => $set['duration_seconds'] ?? null,
                    ];
                }
                if ($working !== []) {
                    $last[$exerciseId] = $working;
                }
            }
        }

        return $last;
    }

    /**
     * Overlays the last performed working loads onto the template's set
     * structure. Warm-ups keep their template values; working sets take the
     * performed loads in order, reusing the last performed load when the template
     * plans more working sets than were done. Per-session data (RPE, done) is not
     * carried into a plan.
     *
     * @param array<int|string, mixed> $templateSets
     * @param list<array{weight_kg:mixed,reps:mixed,duration_seconds:mixed}> $performed non-empty
     *
     * @return list<array<string, mixed>>
     */
    private function applyOverload(array $templateSets, array $performed): array
    {
        $out = [];
        $i = 0;
        foreach ($templateSets as $set) {
            if (! is_array($set)) {
                continue;
            }
            if ($set['is_warmup'] ?? false) {
                $out[] = [
                    'weight_kg' => $set['weight_kg'] ?? null,
                    'reps' => $set['reps'] ?? null,
                    'duration_seconds' => $set['duration_seconds'] ?? null,
                    'is_warmup' => true,
                ];

                continue;
            }
            $src = $performed[$i] ?? $performed[count($performed) - 1];
            $i++;
            $out[] = [
                'weight_kg' => $src['weight_kg'] ?? ($set['weight_kg'] ?? null),
                'reps' => $src['reps'] ?? ($set['reps'] ?? null),
                'duration_seconds' => $src['duration_seconds'] ?? ($set['duration_seconds'] ?? null),
                'is_warmup' => false,
            ];
        }

        return $out;
    }
}
