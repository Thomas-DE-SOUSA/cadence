<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Domain\Port\ExerciseRepository;
use Cadence\Strength\Domain\Port\WorkoutTemplateRepository;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowTemplateEditorController
{
    public function __construct(
        private readonly WorkoutTemplateRepository $templates,
        private readonly ExerciseRepository $exercises,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(?string $id = null): Response
    {
        $tenant = $this->tenantContext->current();

        $template = null;
        if ($id !== null) {
            $found = $this->templates->ofId($id, $tenant);
            if ($found === null) {
                abort(404);
            }
            $template = StrengthView::templateDetail($found);
        }

        $enums = StrengthView::enums();

        return Inertia::render('MuscuTemplate', [
            'catalog' => StrengthView::catalog($this->exercises->forTenant($tenant)),
            'muscles' => $enums['muscles'],
            'equipments' => $enums['equipments'],
            'template' => $template,
        ]);
    }
}
