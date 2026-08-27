<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Application\UseCase\SubmitWellnessCheckIn\SubmitWellnessCheckInInput;
use Cadence\Coaching\Application\UseCase\SubmitWellnessCheckIn\SubmitWellnessCheckInUseCase;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SubmitWellnessCheckInController
{
    public function __construct(
        private readonly SubmitWellnessCheckInUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sleep' => ['required', 'integer', 'between:1,5'],
            'energy' => ['required', 'integer', 'between:1,5'],
            'legs' => ['required', 'integer', 'between:1,5'],
            'motivation' => ['required', 'integer', 'between:1,5'],
            'painLevel' => ['required', 'integer', 'between:0,3'],
            'painLocation' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->useCase->execute(
            new SubmitWellnessCheckInInput(
                (int) $data['sleep'],
                (int) $data['energy'],
                (int) $data['legs'],
                (int) $data['motivation'],
                (int) $data['painLevel'],
                (string) ($data['painLocation'] ?? ''),
                (string) ($data['note'] ?? ''),
            ),
            new ExecutionContext($this->tenantContext->current()),
        );

        return redirect()->route('fitness')->with('status', 'Check-in enregistré — ta forme est affinée.');
    }
}
