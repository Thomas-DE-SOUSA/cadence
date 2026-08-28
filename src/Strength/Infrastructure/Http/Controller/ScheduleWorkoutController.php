<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Application\UseCase\ScheduleWorkout\ScheduleWorkoutInput;
use Cadence\Strength\Application\UseCase\ScheduleWorkout\ScheduleWorkoutUseCase;
use Cadence\Strength\Domain\Exception\TemplateNotFound;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ScheduleWorkoutController
{
    public function __construct(
        private readonly ScheduleWorkoutUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'templateId' => ['required', 'string'],
            'date' => ['required', 'date'],
        ]);

        try {
            $this->useCase->execute(
                new ScheduleWorkoutInput((string) $data['templateId'], (string) $data['date']),
                new ExecutionContext($this->tenantContext->current()),
            );
        } catch (TemplateNotFound) {
            abort(404);
        }

        return back()->with('status', 'Séance posée sur l\'agenda.');
    }
}
