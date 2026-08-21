<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\AssignSessionActivity;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\EventPublisher;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Training\Domain\Exception\CycleNotFound;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Domain\ValueObject\CycleId;
use Cadence\Training\Domain\ValueObject\ProgramId;

/**
 * Links a logged activity to a specific planned day of a cycle. Assigning also
 * attaches the run to the program so the coach objectives count it.
 */
final readonly class AssignSessionActivityUseCase
{
    public function __construct(
        private CycleRepository $cycles,
        private TrainingProgramRepository $programs,
        private Clock $clock,
        private EventPublisher $eventPublisher,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(string $programId, string $cycleId, string $date, ?string $activityId, ExecutionContext $context): void
    {
        $tenant = $context->tenant;
        $cycle = $this->cycles->ofId(CycleId::fromString($cycleId), $tenant);

        if ($cycle === null || $cycle->programId() !== $programId) {
            throw CycleNotFound::withId($cycleId);
        }

        $cycle->linkActivity($date, $activityId);
        $this->cycles->save($cycle);

        if ($activityId !== null) {
            $program = $this->programs->ofId(ProgramId::fromString($programId), $tenant);
            if ($program !== null) {
                $program->assignActivity($activityId, $this->clock->now());
                $events = $program->releaseEvents();

                // Only persist when the assignment actually changed the program;
                // re-assigning an already-linked run is a no-op.
                if ($events !== []) {
                    $this->programs->save($program, $events);
                    $this->eventPublisher->publish($events);
                }
            }
        }

        $this->auditTrail->record('cycle.session_linked', $tenant, $cycleId, [
            'program_id' => $programId,
            'date' => $date,
            'activity_id' => $activityId,
        ], $this->clock->now());
    }
}
