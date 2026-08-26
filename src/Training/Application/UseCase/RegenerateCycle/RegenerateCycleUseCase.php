<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\RegenerateCycle;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Domain\Exception\CycleNotFound;
use Cadence\Training\Domain\Model\Cycle;
use Cadence\Training\Domain\Service\DisciplineResolver;
use Cadence\Training\Domain\Plan\PhaseMaterializer;
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
use Throwable;

/**
 * Rebuilds an existing active cycle in place, adapting it to the athlete's
 * recent performances and feedback. Day-links are preserved by date.
 */
final readonly class RegenerateCycleUseCase
{
    public function __construct(
        private TrainingProgramRepository $programs,
        private CycleRepository $cycles,
        private ActivitySummaryProvider $summaries,
        private CyclePlanner $planner,
        private Clock $clock,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(RegenerateCycleInput $input, ExecutionContext $context): void
    {
        $tenant = $context->tenant;
        $cycle = $this->cycles->ofId(CycleId::fromString($input->cycleId), $tenant);

        if ($cycle === null || $cycle->programId() !== $input->programId) {
            throw CycleNotFound::withId($input->cycleId);
        }

        $program = $this->programs->ofId(ProgramId::fromString($input->programId), $tenant);
        $snapshot = $program?->toSnapshot();

        $planKey = $snapshot['plan_key'] ?? null;
        $plan = $planKey !== null ? TrainingPlanCatalog::byKey($planKey) : null;
        $phase = $plan?->phase($cycle->phaseIndex());

        $current = $cycle->toSnapshot();
        $startDate = trim($input->startDate) !== '' ? $input->startDate : $current['start_date'];
        $weeks = $phase !== null ? $phase->weeks : max(1, $this->weekSpan($current['sessions']));
        $blueprintCycle = $phase !== null ? PhaseMaterializer::toPlannedCycle($phase) : null;

        $planned = $this->plan($snapshot, $input->ressenti, $startDate, $weeks, $phase?->name, $phase?->focus, $blueprintCycle, $tenant, $program?->assignedActivityIds() ?? [], $input->athletePaces, $input->athleteState);

        // Keep the same id so the cycle is replaced, and re-apply day-links by date.
        $rebuilt = Cycle::fromPlan($cycle->id(), $input->programId, $tenant, $planned, $startDate, $cycle->phaseIndex());
        foreach ($current['sessions'] as $s) {
            if (($s['activity_id'] ?? null) !== null) {
                $rebuilt->linkActivity($s['date'], $s['activity_id']);
            }
        }

        $this->cycles->save($rebuilt);
        $this->auditTrail->record('cycle.regenerated', $tenant, $cycle->id()->value, ['program_id' => $input->programId, 'phase_index' => $cycle->phaseIndex()], $this->clock->now());
    }

    /**
     * @param array<string, mixed>|null $snapshot
     * @param list<string> $assignedActivityIds
     */
    private function plan(?array $snapshot, string $ressenti, string $startDate, int $weeks, ?string $phaseName, ?string $phaseFocus, ?PlannedCycle $blueprintCycle, TenantId $tenant, array $assignedActivityIds, string $athletePaces, string $athleteState = ''): PlannedCycle
    {
        $context = new PlannerContext(
            goal: (string) ($snapshot['goal'] ?? ''),
            targetRaceName: (string) ($snapshot['target_race_name'] ?? ''),
            targetRaceDate: isset($snapshot['target_race_date']) ? (string) $snapshot['target_race_date'] : null,
            startDate: $startDate,
            weeks: $weeks,
            ressenti: $ressenti,
            recentPerformance: $this->summariseRecent($this->summaries->summariesFor($tenant, $assignedActivityIds)),
            previousCycle: 'Refonte du cycle courant à partir des dernières performances.',
            phaseName: $phaseName ?? '',
            phaseFocus: $phaseFocus ?? '',
            blueprint: $blueprintCycle !== null ? $this->renderBlueprint($blueprintCycle) : '',
            athletePaces: $athletePaces,
            athleteState: $athleteState,
            disciplinePlaybook: DisciplineResolver::forSnapshot($snapshot ?? [])->playbook(),
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

    /** @param list<array<string, mixed>> $sessions */
    private function weekSpan(array $sessions): int
    {
        $count = count($sessions);

        return $count > 0 ? (int) ceil($count / 7) : 1;
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
            $lines[] = sprintf('%s: %.2f km, allure %d s/km', substr($a->occurredAtIso, 0, 10), $a->distanceMeters / 1000, (int) round($a->averagePaceSecondsPerKm));
        }

        return $lines === [] ? 'Aucune sortie récente enregistrée.' : implode(' ; ', $lines);
    }
}
