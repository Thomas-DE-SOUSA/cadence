<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Provider;

use Cadence\Coaching\Domain\Port\SessionAdjuster;
use Cadence\Coaching\Domain\ValueObject\SessionProposal;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Cadence\Training\Application\UseCase\AdjustSession\AdjustSessionInput;
use Cadence\Training\Application\UseCase\AdjustSession\AdjustSessionUseCase;

/** Applies an accepted coach proposal by delegating to the Training context. */
final class TrainingSessionAdjuster implements SessionAdjuster
{
    public function __construct(private readonly AdjustSessionUseCase $adjust)
    {
    }

    public function apply(string $programId, string $cycleId, SessionProposal $proposal, TenantId $tenant): void
    {
        $this->adjust->execute(
            new AdjustSessionInput(
                programId: $programId,
                cycleId: $cycleId,
                date: $proposal->date,
                type: $proposal->type,
                title: $proposal->title,
                description: $proposal->description,
                targetDistanceMeters: $proposal->targetDistanceMeters,
                targetDurationSeconds: $proposal->targetDurationSeconds,
                targetPaceSecondsPerKm: $proposal->targetPaceSecondsPerKm,
            ),
            new ExecutionContext($tenant),
        );
    }
}
