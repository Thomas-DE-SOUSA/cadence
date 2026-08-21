<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Application\UseCase\UpdateActivity\UpdateActivityUseCase;
use Cadence\Activity\Domain\Exception\ActivityNotFound;
use Cadence\Activity\Domain\Exception\InvalidActivity;
use Cadence\Activity\Infrastructure\Http\Request\UpdateActivityRequest;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Infrastructure\Persistence\ConcurrencyException;
use Illuminate\Http\RedirectResponse;

final class UpdateActivityController
{
    public function __construct(
        private readonly UpdateActivityUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(UpdateActivityRequest $request, string $id): RedirectResponse
    {
        try {
            $this->useCase->execute(
                $request->toInput($id),
                new ExecutionContext($this->tenantContext->current()),
            );
        } catch (ActivityNotFound) {
            abort(404);
        } catch (InvalidActivity) {
            return back()->withErrors(['distance_meters' => 'La distance ne correspond plus aux splits enregistrés.']);
        } catch (ConcurrencyException) {
            return back()->withErrors(['occurred_at' => 'Activité modifiée entre-temps — réessaie.']);
        }

        return redirect()->route('activities.show', $id)->with('status', 'Activité modifiée.');
    }
}
