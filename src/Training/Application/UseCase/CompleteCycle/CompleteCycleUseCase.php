<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\CompleteCycle;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Training\Domain\Exception\CycleNotFound;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\ValueObject\CycleId;

final readonly class CompleteCycleUseCase
{
    public function __construct(
        private CycleRepository $cycles,
        private Clock $clock,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(string $programId, string $cycleId, ExecutionContext $context): void
    {
        $tenant = $context->tenant;
        $cycle = $this->cycles->ofId(CycleId::fromString($cycleId), $tenant);

        if ($cycle === null || $cycle->toSnapshot()['program_id'] !== $programId) {
            throw CycleNotFound::withId($cycleId);
        }

        $cycle->complete();
        $this->cycles->save($cycle);
        $this->auditTrail->record('cycle.completed', $tenant, $cycleId, ['program_id' => $programId], $this->clock->now());
    }
}
