<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure\Http\Controller;

use Cadence\Coaching\Application\FitnessAssessmentService;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Training\Application\UseCase\GenerateCycle\GenerateCycleUseCase;
use Cadence\Training\Domain\Exception\CycleGenerationNotAllowed;
use Cadence\Training\Domain\Exception\ProgramNotFound;
use Cadence\Training\Infrastructure\Http\Request\GenerateCycleRequest;
use Illuminate\Http\RedirectResponse;
use Throwable;

final class GenerateCycleController
{
    public function __construct(
        private readonly GenerateCycleUseCase $useCase,
        private readonly FitnessAssessmentService $fitness,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(GenerateCycleRequest $request, string $id): RedirectResponse
    {
        $tenant = $this->tenantContext->current();

        try {
            $this->useCase->execute(
                $request->toInput($id, $this->fitness->pacesSummaryFor($tenant)),
                new ExecutionContext($tenant),
            );
        } catch (ProgramNotFound) {
            abort(404);
        } catch (CycleGenerationNotAllowed $e) {
            return back()->withErrors(['cycle' => $e->getMessage()]);
        } catch (Throwable $e) {
            return back()->withErrors(['ressenti' => $e->getMessage()]);
        }

        return redirect()->route('programs.show', $id)->with('status', 'Cycle généré par l\'IA.');
    }
}
