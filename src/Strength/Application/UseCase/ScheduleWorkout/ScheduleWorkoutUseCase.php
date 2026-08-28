<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\ScheduleWorkout;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Strength\Domain\Enum\WorkoutStatus;
use Cadence\Strength\Domain\Exception\TemplateNotFound;
use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Port\WorkoutTemplateRepository;

/**
 * Places a template on an agenda day: creates a PLANNED session pre-filled with
 * the template's exercises and target sets, ready to be done and adjusted.
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
        $id = $this->ids->generate();

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
            'exercises' => $snap['exercises'],
        ]);

        $this->sessions->save($session);

        return $id;
    }
}
