<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application;

use Cadence\Coaching\Domain\Port\AthleteHistoryProvider;
use Cadence\Coaching\Domain\Service\FitnessAssessor;
use Cadence\Coaching\Domain\ValueObject\FitnessSnapshot;
use Cadence\Shared\Domain\TenantId;

final readonly class FitnessAssessmentService
{
    public function __construct(
        private AthleteHistoryProvider $history,
        private FitnessAssessor $assessor,
    ) {
    }

    public function forTenant(TenantId $tenant): ?FitnessSnapshot
    {
        return $this->assessor->assess($this->history->recentFor($tenant));
    }
}
