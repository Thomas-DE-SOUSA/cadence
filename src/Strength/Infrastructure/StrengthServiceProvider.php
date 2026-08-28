<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure;

use Cadence\Strength\Domain\Port\ExerciseRepository;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Infrastructure\Http\Controller\AddCustomExerciseController;
use Cadence\Strength\Infrastructure\Http\Controller\LogStrengthSessionController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowMuscuController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowSessionEditorController;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\EloquentExerciseRepository;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\EloquentStrengthSessionRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class StrengthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExerciseRepository::class, EloquentExerciseRepository::class);
        $this->app->bind(StrengthSessionRepository::class, EloquentStrengthSessionRepository::class);
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->prefix('muscu')->group(function (): void {
            Route::get('/', ShowMuscuController::class)->name('muscu');
            Route::get('/nouveau', ShowSessionEditorController::class)->name('muscu.new');
            Route::post('/', LogStrengthSessionController::class)->name('muscu.log');
            Route::post('/exercices', AddCustomExerciseController::class)->name('muscu.exercises.add');
            Route::get('/{id}/modifier', ShowSessionEditorController::class)->name('muscu.edit');
        });
    }
}
