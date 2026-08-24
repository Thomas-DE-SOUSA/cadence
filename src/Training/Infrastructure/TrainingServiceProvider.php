<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure;

use Cadence\Training\Domain\Port\ActivitySummaryProvider;
use Cadence\Training\Domain\Port\CyclePlanner;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Infrastructure\Ai\GeminiCyclePlanner;
use Cadence\Training\Infrastructure\Http\Controller\AssignActivityController;
use Cadence\Training\Infrastructure\Http\Controller\AssignSessionActivityController;
use Cadence\Training\Infrastructure\Http\Controller\CompleteCycleController;
use Cadence\Training\Infrastructure\Http\Controller\CreateProgramController;
use Cadence\Training\Infrastructure\Http\Controller\GenerateCycleController;
use Cadence\Training\Infrastructure\Http\Controller\RegenerateCycleController;
use Cadence\Training\Infrastructure\Http\Controller\ShowProgramController;
use Cadence\Training\Infrastructure\Http\Controller\ShowProgramsController;
use Cadence\Training\Infrastructure\Http\Controller\UnassignActivityController;
use Cadence\Training\Infrastructure\Persistence\Eloquent\EloquentCycleRepository;
use Cadence\Training\Infrastructure\Persistence\Eloquent\EloquentTrainingProgramRepository;
use Cadence\Training\Infrastructure\Provider\EloquentActivitySummaryProvider;
use Cadence\Training\Infrastructure\Read\ProgramView;
use Cadence\Shared\Infrastructure\Ai\GeminiClient;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

final class TrainingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TrainingProgramRepository::class, EloquentTrainingProgramRepository::class);
        $this->app->bind(ActivitySummaryProvider::class, EloquentActivitySummaryProvider::class);
        $this->app->bind(CycleRepository::class, EloquentCycleRepository::class);
        $this->app->bind(CyclePlanner::class, static fn (): GeminiCyclePlanner => new GeminiCyclePlanner(
            new GeminiClient((string) config('services.gemini.key', ''), (string) config('services.gemini.model')),
        ));
    }

    public function boot(): void
    {
        Route::middleware('web')->prefix('programme')->group(function (): void {
            Route::get('/', ShowProgramsController::class)->name('programs.index');
            Route::get('/nouveau', fn () => Inertia::render('ProgramForm', ['plans' => ProgramView::plans()]))->name('programs.create');
            Route::post('/', CreateProgramController::class)->name('programs.store');
            Route::get('/{id}', ShowProgramController::class)->name('programs.show');
            Route::post('/{id}/assigner', AssignActivityController::class)->name('programs.assign');
            Route::post('/{id}/retirer', UnassignActivityController::class)->name('programs.unassign');
            Route::post('/{id}/generer-cycle', GenerateCycleController::class)->name('programs.generate-cycle');
            Route::post('/{id}/cycles/{cycleId}/terminer', CompleteCycleController::class)->name('programs.complete-cycle');
            Route::post('/{id}/cycles/{cycleId}/refaire', RegenerateCycleController::class)->name('programs.regenerate-cycle');
            Route::post('/{id}/cycles/{cycleId}/jour', AssignSessionActivityController::class)->name('programs.assign-day');
        });
    }
}
