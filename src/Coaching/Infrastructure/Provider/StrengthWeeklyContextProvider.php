<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Provider;

use Cadence\Coaching\Domain\Port\StrengthContextProvider;
use Cadence\Coaching\Domain\ValueObject\StrengthWeekSummary;
use Cadence\Coaching\Infrastructure\Read\StrengthWeeklyBrief;
use Cadence\Shared\Domain\TenantId;
use Cadence\Strength\Domain\Model\Exercise;
use Cadence\Strength\Domain\Port\ExerciseRepository;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Port\WeightEntryRepository;
use DateTimeImmutable;

/**
 * Bridges the Strength context into the weekly coach: fetches the tenant's
 * recent sessions, the exercise→muscle map, and the body-weight readings, then
 * hands them to {@see StrengthWeeklyBrief} to shape one Mon–Sun summary.
 */
final readonly class StrengthWeeklyContextProvider implements StrengthContextProvider
{
    public function __construct(
        private StrengthSessionRepository $sessions,
        private ExerciseRepository $exercises,
        private WeightEntryRepository $weight,
    ) {
    }

    public function weekSummary(TenantId $tenant, string $today): StrengthWeekSummary
    {
        // 21 days back covers the current week, the previous week, and a small
        // buffer for the days-since-last look-back.
        $since = (new DateTimeImmutable($today))->modify('-21 days')->format('Y-m-d');

        $muscleOf = [];
        foreach ($this->exercises->forTenant($tenant) as $exercise) {
            /** @var Exercise $exercise */
            $muscleOf[$exercise->id] = $exercise->primaryMuscle;
        }

        return StrengthWeeklyBrief::summarise(
            $this->sessions->forTenant($tenant, 200),
            $muscleOf,
            $this->weight->since($tenant, $since),
            $today,
        );
    }
}
