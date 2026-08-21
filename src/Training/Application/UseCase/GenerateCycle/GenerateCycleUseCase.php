<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\GenerateCycle;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Training\Domain\Exception\ProgramNotFound;
use Cadence\Training\Domain\Model\Cycle;
use Cadence\Training\Domain\Port\ActivitySummaryProvider;
use Cadence\Training\Domain\Port\CyclePlanner;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Domain\ValueObject\ActivitySummary;
use Cadence\Training\Domain\ValueObject\CycleId;
use Cadence\Training\Domain\ValueObject\PlannerContext;
use Cadence\Training\Domain\ValueObject\ProgramId;

final readonly class GenerateCycleUseCase
{
    public function __construct(
        private TrainingProgramRepository $programs,
        private CycleRepository $cycles,
        private ActivitySummaryProvider $summaries,
        private CyclePlanner $planner,
        private IdGenerator $ids,
        private Clock $clock,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(GenerateCycleInput $input, ExecutionContext $context): string
    {
        $tenant = $context->tenant;
        $program = $this->programs->ofId(ProgramId::fromString($input->programId), $tenant);
        if ($program === null) {
            throw ProgramNotFound::withId(ProgramId::fromString($input->programId));
        }

        $snapshot = $program->toSnapshot();
        $recent = $this->summariseRecent($this->summaries->summariesFor($tenant, $program->assignedActivityIds()));
        $previous = $this->summarisePrevious($this->cycles->latestForProgram($input->programId, $tenant));

        $plan = $this->planner->plan(new PlannerContext(
            goal: $snapshot['goal'],
            targetRaceName: $snapshot['target_race_name'],
            targetRaceDate: $snapshot['target_race_date'],
            startDate: $input->startDate,
            weeks: $input->weeks,
            ressenti: $input->ressenti,
            recentPerformance: $recent,
            previousCycle: $previous,
        ));

        $cycle = Cycle::fromPlan(CycleId::generate($this->ids), $input->programId, $tenant, $plan, $input->startDate);
        $this->cycles->save($cycle);
        $this->auditTrail->record('cycle.generated', $tenant, $cycle->id()->value, ['program_id' => $input->programId], $this->clock->now());

        return $cycle->id()->value;
    }

    /** @param list<ActivitySummary> $summaries */
    private function summariseRecent(array $summaries): string
    {
        $lines = [];
        foreach (array_slice($summaries, 0, 8) as $a) {
            $lines[] = sprintf(
                '%s: %.2f km, allure %d s/km',
                substr($a->occurredAtIso, 0, 10),
                $a->distanceMeters / 1000,
                (int) round($a->averagePaceSecondsPerKm),
            );
        }

        return $lines === [] ? 'Aucune sortie récente enregistrée.' : implode(' ; ', $lines);
    }

    private function summarisePrevious(?Cycle $cycle): string
    {
        if ($cycle === null) {
            return 'Aucun cycle précédent — c\'est le premier.';
        }

        $s = $cycle->toSnapshot();

        return sprintf('Cycle précédent "%s" (%s), %d séances, du %s au %s.', $s['name'], $s['focus'], count($s['sessions']), $s['start_date'], $s['end_date']);
    }
}
