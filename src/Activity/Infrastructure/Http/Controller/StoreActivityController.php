<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityUseCase;
use Cadence\Activity\Domain\Exception\DuplicateActivity;
use Cadence\Activity\Infrastructure\Http\Request\RecordActivityRequest;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

/**
 * Web (Inertia) adapter for recording an activity. Same use case as the JSON
 * API controller; this one answers a form post with a redirect.
 */
final class StoreActivityController
{
    public function __construct(
        private readonly RecordActivityUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(RecordActivityRequest $request): RedirectResponse
    {
        try {
            $output = $this->useCase->execute(
                $request->toInput(),
                new ExecutionContext($this->tenantContext->current()),
            );
        } catch (DuplicateActivity) {
            return back()->withErrors(['occurred_at' => 'Une sortie très similaire est déjà enregistrée à cette date.']);
        }

        return redirect()
            ->route('activities.show', $output->activityId)
            ->with('status', 'Activité enregistrée.');
    }
}
