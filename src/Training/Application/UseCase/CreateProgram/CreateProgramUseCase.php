<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\CreateProgram;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\EventPublisher;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Training\Domain\Enum\ObjectiveType;
use Cadence\Training\Domain\Enum\ProgramPriority;
use Cadence\Training\Domain\Event\ProgramCreated;
use Cadence\Training\Domain\Model\Cycle;
use Cadence\Training\Domain\Model\Objective;
use Cadence\Training\Domain\Model\TrainingProgram;
use Cadence\Training\Domain\Plan\PhaseMaterializer;
use Cadence\Training\Domain\Plan\TrainingPlanCatalog;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Domain\ValueObject\CycleId;
use Cadence\Training\Domain\ValueObject\ProgramId;

final readonly class CreateProgramUseCase
{
    public function __construct(
        private TrainingProgramRepository $programs,
        private CycleRepository $cycles,
        private IdGenerator $ids,
        private Clock $clock,
        private EventPublisher $eventPublisher,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(CreateProgramInput $input, ExecutionContext $context): string
    {
        $tenant = $context->tenant;
        $id = ProgramId::generate($this->ids);

        $objectives = array_map(
            fn (ObjectiveInput $o): Objective => new Objective(
                $this->ids->generate(),
                ObjectiveType::from($o->type),
                $o->label,
                $o->targetDistanceMeters,
                $o->targetSeconds,
                $o->targetPaceSecondsPerKm,
                $o->targetCount,
            ),
            $input->objectives,
        );

        $planKey = $input->planKey !== null && TrainingPlanCatalog::byKey($input->planKey) !== null
            ? $input->planKey
            : null;

        $program = TrainingProgram::create(
            $id,
            $tenant,
            $input->name,
            $input->goal,
            $input->targetRaceName,
            $input->targetRaceDate,
            $input->startDate,
            $input->endDate,
            ProgramPriority::from($input->priority),
            $objectives,
            $this->clock->now(),
            $planKey,
        );

        $events = $program->releaseEvents();
        $this->programs->save($program, $events);
        $this->eventPublisher->publish($events);
        $this->auditTrail->record(ProgramCreated::NAME, $tenant, $id->value, $program->toSnapshot(), $this->clock->now());

        $this->materialiseFirstCycle($program);

        return $id->value;
    }

    /** Lay out the plan's first phase as a concrete, active cycle. */
    private function materialiseFirstCycle(TrainingProgram $program): void
    {
        $planKey = $program->planKey();
        if ($planKey === null) {
            return;
        }

        $plan = TrainingPlanCatalog::byKey($planKey);
        $firstPhase = $plan?->phase(0);
        if ($firstPhase === null) {
            return;
        }

        $cycle = Cycle::fromPlan(
            CycleId::generate($this->ids),
            $program->id()->value,
            $program->tenant(),
            PhaseMaterializer::toPlannedCycle($firstPhase),
            $program->startDate(),
            phaseIndex: 0,
        );

        $this->cycles->save($cycle);
        $this->auditTrail->record('cycle.materialised', $program->tenant(), $cycle->id()->value, ['program_id' => $program->id()->value, 'phase_index' => 0], $this->clock->now());
    }
}
