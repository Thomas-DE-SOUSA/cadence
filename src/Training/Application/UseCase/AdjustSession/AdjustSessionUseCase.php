<?php

declare(strict_types=1);

namespace Cadence\Training\Application\UseCase\AdjustSession;

use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Training\Domain\Enum\SessionType;
use Cadence\Training\Domain\Exception\CycleNotFound;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\ValueObject\CycleId;

/** Replaces a single planned day in a cycle (used when the coach's proposal is accepted). */
final readonly class AdjustSessionUseCase
{
    public function __construct(
        private CycleRepository $cycles,
        private Clock $clock,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(AdjustSessionInput $input, ExecutionContext $context): void
    {
        $tenant = $context->tenant;
        $cycle = $this->cycles->ofId(CycleId::fromString($input->cycleId), $tenant);

        if ($cycle === null || $cycle->programId() !== $input->programId) {
            throw CycleNotFound::withId($input->cycleId);
        }

        $cycle->replaceSession(
            $input->date,
            SessionType::tryFrom($input->type) ?? SessionType::EASY,
            $input->title,
            $input->description,
            $input->targetDistanceMeters,
            $input->targetDurationSeconds,
            $input->targetPaceSecondsPerKm,
        );

        $this->cycles->save($cycle);
        $this->auditTrail->record('cycle.session_adjusted', $tenant, $input->cycleId, [
            'program_id' => $input->programId,
            'date' => $input->date,
            'type' => $input->type,
        ], $this->clock->now());
    }
}
