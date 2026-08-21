<?php

declare(strict_types=1);

namespace Cadence\Training\Infrastructure;

use Cadence\Training\Domain\Port\ActivitySummaryProvider;
use Cadence\Training\Domain\Port\CyclePlanner;
use Cadence\Training\Domain\Port\CycleRepository;
use Cadence\Training\Domain\Port\TrainingProgramRepository;
use Cadence\Training\Infrastructure\Ai\ClaudeCyclePlanner;
use Cadence\Training\Infrastructure\Http\Controller\AssignActivityController;
use Cadence\Training\Infrastructure\Http\Controller\CreateProgramController;
use Cadence\Training\Infrastructure\Http\Controller\GenerateCycleController;
use Cadence\Training\Infrastructure\Http\Controller\ShowProgramController;
use Cadence\Training\Infrastructure\Http\Controller\ShowProgramsController;
use Cadence\Training\Infrastructure\Http\Controller\UnassignActivityController;
use Cadence\Training\Infrastructure\Persistence\Eloquent\EloquentCycleRepository;
use Cadence\Training\Infrastructure\Persistence\Eloquent\EloquentTrainingProgramRepository;
use Cadence\Training\Infrastructure\Provider\EloquentActivitySummaryProvider;
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
        $this->app->bind(CyclePlanner::class, static fn (): ClaudeCyclePlanner => new ClaudeCyclePlanner(
            (string) config('services.anthropic.key'),
        ));
    }

    public function boot(): void
    {
        Route::prefix('programme')->group(function (): void {
            Route::get('/', ShowProgramsController::class)->name('programs.index');
            Route::get('/nouveau', fn () => Inertia::render('ProgramForm'))->name('programs.create');
            Route::post('/', CreateProgramController::class)->name('programs.store');
            Route::get('/{id}', ShowProgramController::class)->name('programs.show');
            Route::post('/{id}/assigner', AssignActivityController::class)->name('programs.assign');
            Route::post('/{id}/retirer', UnassignActivityController::class)->name('programs.unassign');
            Route::post('/{id}/generer-cycle', GenerateCycleController::class)->name('programs.generate-cycle');
        });
    }
}
