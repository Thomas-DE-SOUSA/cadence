<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Domain\Port\ExerciseRepository;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowSessionEditorController
{
    public function __construct(
        private readonly StrengthSessionRepository $sessions,
        private readonly ExerciseRepository $exercises,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(?string $id = null): Response
    {
        $tenant = $this->tenantContext->current();
        $recent = $this->sessions->forTenant($tenant);

        $session = null;
        if ($id !== null) {
            $found = $this->sessions->ofId($id, $tenant);
            if ($found === null) {
                abort(404);
            }
            $session = StrengthView::detail($found);
        }

        // "Last time" = the most recent DONE session of each exercise, excluding
        // the one being performed, so the athlete sees a real reference to beat.
        $history = array_values(array_filter(
            $recent,
            static fn ($s): bool => $s->status()->isDone() && $s->id() !== $id,
        ));

        $enums = StrengthView::enums();

        return Inertia::render('MuscuSession', [
            'catalog' => StrengthView::catalog($this->exercises->forTenant($tenant)),
            'muscles' => $enums['muscles'],
            'equipments' => $enums['equipments'],
            'session' => $session,
            'lastByExercise' => StrengthView::lastByExercise($history),
        ]);
    }
}
