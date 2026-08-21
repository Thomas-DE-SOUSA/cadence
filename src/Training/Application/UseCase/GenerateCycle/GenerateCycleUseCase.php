<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\GenerateCycle;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Domain\TenantId;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Training\Domain\Exception\CycleGenerationNotAllowed;
use Cadence\Training\Domain\Exception\ProgramNotFound;
use Cadence\Training\Domain\Model\Cycle;
use Cadence\Training\Domain\Plan\PhaseMaterializer;
use Cadence\Training\Domain\Plan\PlanPhase;
use Cadence\Training\Domain\Plan\TrainingPlanCatalog;
use Cadence\Training\Domain\Port\ActivitySummaryProvider;
use Cadence\Training\Domain\Port\CyclePlanner;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Domain\ValueObject\ActivitySummary;
use Cadence\Training\Domain\ValueObject\CycleId;
use Cadence\Training\Domain\ValueObject\PlannedCycle;
use Cadence\Training\Domain\ValueObject\PlannerContext;
use Cadence\Training\Domain\ValueObject\ProgramId;
use DateTimeImmutable;
use Throwable;

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

        $existing = $this->cycles->forProgram($input->programId, $tenant);
        $latest = $existing === [] ? null : $existing[count($existing) - 1];

        // The next cycle only unlocks once the current one is marked completed.
        if ($latest !== null && ! $latest->isCompleted()) {
            throw CycleGenerationNotAllowed::currentCycleNotCompleted();
        }

        $snapshot = $program->toSnapshot();
        $plan = $snapshot['plan_key'] !== null ? TrainingPlanCatalog::byKey($snapshot['plan_key']) : null;
        $nextIndex = $latest !== null ? $latest->phaseIndex() + 1 : 0;

        $phase = $plan?->phase($nextIndex);
        if ($plan !== null && $phase === null) {
            throw CycleGenerationNotAllowed::roadmapFinished();
        }

        $startDate = $this->nextStartDate($input, $latest?->endDate(), $program->startDate());
        $weeks = $phase !== null ? $phase->weeks : max(1, $input->weeks);
        $blueprintCycle = $phase !== null ? PhaseMaterializer::toPlannedCycle($phase) : null;

        $planned = $this->planWithFallback($snapshot, $input, $startDate, $weeks, $phase, $blueprintCycle, $tenant, $program->assignedActivityIds(), $latest);

        $cycle = Cycle::fromPlan(
            CycleId::generate($this->ids),
            $input->programId,
            $tenant,
            $planned,
            $startDate,
            phaseIndex: $nextIndex,
        );
        $this->cycles->save($cycle);
        $this->auditTrail->record('cycle.generated', $tenant, $cycle->id()->value, ['program_id' => $input->programId, 'phase_index' => $nextIndex], $this->clock->now());

        return $cycle->id()->value;
    }

    /**
     * Ask the AI to adapt the expert blueprint to the athlete; fall back to the
     * blueprint itself when the AI is unavailable, so generation always works.
     *
     * @param array<string, mixed> $snapshot
     * @param list<string> $assignedActivityIds
     */
    private function planWithFallback(
        array $snapshot,
        GenerateCycleInput $input,
        string $startDate,
        int $weeks,
        ?PlanPhase $phase,
        ?PlannedCycle $blueprintCycle,
        TenantId $tenant,
        array $assignedActivityIds,
        ?Cycle $latest,
    ): PlannedCycle {
        $recent = $this->summariseRecent($this->summaries->summariesFor($tenant, $assignedActivityIds));
        $previous = $this->summarisePrevious($latest);

        $context = new PlannerContext(
            goal: (string) $snapshot['goal'],
            targetRaceName: (string) $snapshot['target_race_name'],
            targetRaceDate: $snapshot['target_race_date'] !== null ? (string) $snapshot['target_race_date'] : null,
            startDate: $startDate,
            weeks: $weeks,
            ressenti: $input->ressenti,
            recentPerformance: $recent,
            previousCycle: $previous,
            phaseName: $phase !== null ? $phase->name : '',
            phaseFocus: $phase !== null ? $phase->focus : '',
            blueprint: $blueprintCycle !== null ? $this->renderBlueprint($blueprintCycle) : '',
            athletePaces: $input->athletePaces,
        );

        try {
            return $this->planner->plan($context);
        } catch (Throwable $e) {
            if ($blueprintCycle !== null) {
                return $blueprintCycle;
            }

            throw $e;
        }
    }

    private function nextStartDate(GenerateCycleInput $input, ?string $previousEnd, string $programStart): string
    {
        if (trim($input->startDate) !== '') {
            return $input->startDate;
        }

        if ($previousEnd !== null) {
            return (new DateTimeImmutable($previousEnd))->modify('+1 day')->format('Y-m-d');
        }

        return $programStart;
    }

    private function renderBlueprint(PlannedCycle $cycle): string
    {
        $lines = [$cycle->name.' — '.$cycle->focus];
        foreach ($cycle->sessions as $s) {
            $lines[] = sprintf('J+%d: %s — %s (%s)', $s->dayOffset, $s->type, $s->title, $s->description);
        }

        return implode("\n", $lines);
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
