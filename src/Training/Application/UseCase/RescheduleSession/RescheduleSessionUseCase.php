<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\RescheduleSession;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Training\Domain\Exception\CycleNotFound;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\ValueObject\CycleId;

/** Moves a planned session of a cycle to another day. */
final readonly class RescheduleSessionUseCase
{
    public function __construct(
        private CycleRepository $cycles,
        private Clock $clock,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(string $programId, string $cycleId, string $fromDate, string $toDate, ExecutionContext $context): void
    {
        $tenant = $context->tenant;
        $cycle = $this->cycles->ofId(CycleId::fromString($cycleId), $tenant);

        if ($cycle === null || $cycle->programId() !== $programId) {
            throw CycleNotFound::withId($cycleId);
        }

        $cycle->rescheduleSession($fromDate, $toDate);
        $this->cycles->save($cycle);

        $this->auditTrail->record('cycle.session_rescheduled', $tenant, $cycleId, [
            'program_id' => $programId,
            'from' => $fromDate,
            'to' => $toDate,
        ], $this->clock->now());
    }
}
