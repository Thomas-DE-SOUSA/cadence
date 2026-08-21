<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\AssignActivity;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\EventPublisher;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Training\Domain\Event\ActivityAssignedToProgram;
use Cadence\Training\Domain\Exception\ProgramNotFound;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Domain\ValueObject\ProgramId;

final readonly class AssignActivityUseCase
{
    public function __construct(
        private TrainingProgramRepository $programs,
        private Clock $clock,
        private EventPublisher $eventPublisher,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(string $programId, string $activityId, ExecutionContext $context): void
    {
        $tenant = $context->tenant;
        $id = ProgramId::fromString($programId);

        $program = $this->programs->ofId($id, $tenant);
        if ($program === null) {
            throw ProgramNotFound::withId($id);
        }

        $program->assignActivity($activityId, $this->clock->now());

        $events = $program->releaseEvents();
        $this->programs->save($program, $events);
        $this->eventPublisher->publish($events);
        $this->auditTrail->record(
            ActivityAssignedToProgram::NAME,
            $tenant,
            $id->value,
            ['activity_id' => $activityId],
            $this->clock->now(),
        );
    }
}
