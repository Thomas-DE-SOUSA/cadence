<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Cadence\Activity\Infrastructure\Read\HistoryView;
use Cadence\Activity\Infrastructure\Read\PaceView;
use Cadence\Athlete\Domain\Port\AthleteRepository;
use Cadence\Athlete\Infrastructure\Read\AthleteGoalView;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Training\Infrastructure\Read\RaceGoalView;
use Cadence\Training\Infrastructure\Read\WeekPlanView;
use DateTimeImmutable;
use Inertia\Inertia;
use Inertia\Response;

final class ShowHistoryController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
        private readonly AthleteRepository $athletes,
    ) {
    }

    public function __invoke(): Response
    {
        $tenant = $this->tenantContext->current();
        $tenantId = $tenant->value;
        $today = $this->clock->now();

        $activities = array_values(
            ActivityModel::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('occurred_at')
                ->get()
                ->all(),
        );

        // Planned session type for each day of the current Monday–Sunday week,
        // so the streak strip can show a deliberate rest day instead of a hole.
        $monday = $today->modify('-'.((int) $today->format('N') - 1).' days');
        $plannedByDate = WeekPlanView::typesByDate(
            $tenantId,
            $monday->format('Y-m-d'),
            $monday->modify('+6 days')->format('Y-m-d'),
        );

        $profile = $this->athletes->ofTenant($tenant)?->profile();
        $name = $profile !== null && trim($profile->displayName) !== '' ? trim($profile->displayName) : 'Athlète';
        $athlete = [
            'name' => $name,
            'initial' => mb_strtoupper(mb_substr($name, 0, 1)),
            'age' => self::age($profile?->birthDate, $today),
        ];

        $paceVdot = PaceView::build($activities)['vdot'];
        $vdot = is_numeric($paceVdot) ? (float) $paceVdot : null;
        $goal = AthleteGoalView::forTenant($this->athletes, $tenant) ?? RaceGoalView::forTenant($tenantId);

        return Inertia::render(
            'History',
            HistoryView::build($activities, $today, $plannedByDate, $athlete, $vdot, $goal),
        );
    }

    private static function age(?string $birthDate, DateTimeImmutable $today): ?int
    {
        if ($birthDate === null || trim($birthDate) === '') {
            return null;
        }

        return (int) $today->diff(new DateTimeImmutable($birthDate))->y;
    }
}
