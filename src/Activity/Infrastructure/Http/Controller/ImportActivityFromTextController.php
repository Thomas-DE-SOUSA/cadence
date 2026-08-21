<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure\Http\Controller;

use Cadence\Activity\Application\Port\Exception\ActivityTextUnparseable;
use Cadence\Activity\Application\UseCase\ImportActivityFromText\ImportActivityFromTextUseCase;
use Cadence\Activity\Domain\Exception\DuplicateActivity;
use Cadence\Activity\Infrastructure\Http\Request\ImportActivityTextRequest;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\RedirectResponse;

final class ImportActivityFromTextController
{
    public function __construct(
        private readonly ImportActivityFromTextUseCase $useCase,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(ImportActivityTextRequest $request): RedirectResponse
    {
        try {
            $output = $this->useCase->execute(
                $request->pastedText(),
                new ExecutionContext($this->tenantContext->current()),
                $request->occurredAtOverride(),
            );
        } catch (DuplicateActivity) {
            return back()->withErrors(['text' => 'Cette sortie semble déjà enregistrée.']);
        } catch (ActivityTextUnparseable $e) {
            return back()->withErrors(['text' => $e->getMessage()]);
        }

        if (! $output->imported || $output->activityId === null) {
            return back()->with('status', 'Cette activité est déjà importée.');
        }

        return redirect()
            ->route('activities.show', $output->activityId)
            ->with('status', 'Activité importée depuis le texte collé.');
    }
}
