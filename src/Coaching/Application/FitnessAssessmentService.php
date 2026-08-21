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

    /** A one-line paces summary for prompting the cycle planner (empty when unknown). */
    public function pacesSummaryFor(TenantId $tenant): string
    {
        $snapshot = $this->forTenant($tenant);
        if ($snapshot === null) {
            return '';
        }

        $p = $snapshot->paces;
        $fmt = static fn (int $s): string => sprintf('%d s/km (%d:%02d)', $s, intdiv($s, 60), $s % 60);

        return sprintf(
            'VDOT ~%.1f. Allures perso : E %s, M %s, T %s, I %s, R %s.',
            $snapshot->vdot,
            $fmt($p->easy),
            $fmt($p->marathon),
            $fmt($p->threshold),
            $fmt($p->interval),
            $fmt($p->repetition),
        );
    }
}
