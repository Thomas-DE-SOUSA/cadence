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
 * per exercise, with the weights actually performed last time
 * (progressive-overload memory) — falling back to the template's target sets
 * for exercises never done before.
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
     * The sets performed the most recent time each exercise was actually done
     * (DONE sessions only; the repo returns them most-recent-first).
     *
     * @return array<string, list<array<string, mixed>>> exercise_id → its last performed sets
     */
    private function lastPerformedSets(TenantId $tenant): array
    {
        $last = [];
        foreach ($this->sessions->forTenant($tenant) as $session) {
            if (! $session->status()->isDone()) {
                continue;
            }
            foreach ($session->toSnapshot()['exercises'] as $exercise) {
                if (! is_array($exercise) || ! isset($exercise['exercise_id'])) {
                    continue;
                }
                $exerciseId = (string) $exercise['exercise_id'];
                if (! isset($last[$exerciseId]) && isset($exercise['sets']) && is_array($exercise['sets'])) {
                    $last[$exerciseId] = array_values($exercise['sets']);
                }
            }
        }

        return $last;
    }
}
