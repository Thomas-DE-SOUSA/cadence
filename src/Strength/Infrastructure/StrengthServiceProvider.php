<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure;

use Cadence\Shared\Infrastructure\Ai\AnthropicClient;
use Cadence\Strength\Application\Port\FoodEstimator;
use Cadence\Strength\Domain\Port\ExerciseRepository;
use Cadence\Strength\Domain\Port\StrengthProfileRepository;
use Cadence\Strength\Domain\Port\NutritionEntryRepository;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Port\WeightEntryRepository;
use Cadence\Strength\Domain\Port\WorkoutTemplateRepository;
use Cadence\Strength\Infrastructure\Ai\AnthropicFoodEstimator;
use Cadence\Strength\Infrastructure\Http\Controller\AddCustomExerciseController;
use Cadence\Strength\Infrastructure\Http\Controller\DeleteNutritionEntryController;
use Cadence\Strength\Infrastructure\Http\Controller\EstimateNutritionController;
use Cadence\Strength\Infrastructure\Http\Controller\LogNutritionController;
use Cadence\Strength\Infrastructure\Http\Controller\SaveStrengthProfileController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowStrengthProfileController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowNutritionController;
use Cadence\Strength\Infrastructure\Http\Controller\DeleteTemplateController;
use Cadence\Strength\Infrastructure\Http\Controller\LogStrengthSessionController;
use Cadence\Strength\Infrastructure\Http\Controller\LogWeightEntryController;
use Cadence\Strength\Infrastructure\Http\Controller\RemoveScheduledWorkoutController;
use Cadence\Strength\Infrastructure\Http\Controller\SaveTemplateController;
use Cadence\Strength\Infrastructure\Http\Controller\ScheduleWorkoutController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowAgendaController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowExerciseHistoryController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowProgressionController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowSessionEditorController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowTemplateEditorController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowTemplatesController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowWeightController;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\EloquentExerciseRepository;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\EloquentStrengthProfileRepository;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\EloquentNutritionEntryRepository;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\EloquentStrengthSessionRepository;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\EloquentWeightEntryRepository;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\EloquentWorkoutTemplateRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class StrengthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExerciseRepository::class, EloquentExerciseRepository::class);
        $this->app->bind(StrengthSessionRepository::class, EloquentStrengthSessionRepository::class);
        $this->app->bind(WorkoutTemplateRepository::class, EloquentWorkoutTemplateRepository::class);
        $this->app->bind(StrengthProfileRepository::class, EloquentStrengthProfileRepository::class);
        $this->app->bind(WeightEntryRepository::class, EloquentWeightEntryRepository::class);
        $this->app->bind(NutritionEntryRepository::class, EloquentNutritionEntryRepository::class);
        // Food estimation runs on Claude (Haiku) — reliable, strict-JSON output,
        // no free-tier overload spikes. Swap the model via ANTHROPIC_MODEL.
        $this->app->bind(FoodEstimator::class, fn (): AnthropicFoodEstimator => new AnthropicFoodEstimator(
            new AnthropicClient(
                (string) config('services.anthropic.key', ''),
                (string) config('services.anthropic.model', 'claude-haiku-4-5'),
            ),
        ));
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->prefix('strength')->group(function (): void {
            // Schedule (home) + progression.
            Route::get('/', ShowAgendaController::class)->name('strength');
            Route::get('/progression', ShowProgressionController::class)->name('strength.progression');
            Route::get('/exercise/{exerciseId}/history', ShowExerciseHistoryController::class)->name('strength.exercise.history');

            // Body-weight tracker (morning/evening readings → weekly averages).
            Route::get('/weight', ShowWeightController::class)->name('strength.weight');
            Route::post('/weight', LogWeightEntryController::class)->name('strength.weight.save');

            // Nutrition: daily food log (AI-estimated) vs. lean-bulk targets.
            Route::get('/nutrition', ShowNutritionController::class)->name('strength.nutrition');
            Route::post('/nutrition', LogNutritionController::class)->name('strength.nutrition.log');
            Route::post('/nutrition/{id}/estimate', EstimateNutritionController::class)->name('strength.nutrition.estimate');
            Route::post('/nutrition/{id}/delete', DeleteNutritionEntryController::class)->name('strength.nutrition.delete');

            // Strength profile (goal, level, equipment, priorities…).
            Route::get('/profile', ShowStrengthProfileController::class)->name('strength.profile');
            Route::post('/profile', SaveStrengthProfileController::class)->name('strength.profile.save');

            // Session templates (the reusable library).
            Route::get('/sessions', ShowTemplatesController::class)->name('strength.templates');
            Route::get('/sessions/new', ShowTemplateEditorController::class)->name('strength.templates.new');
            Route::post('/sessions', SaveTemplateController::class)->name('strength.templates.save');
            Route::get('/sessions/{id}/edit', ShowTemplateEditorController::class)->name('strength.templates.edit');
            Route::post('/sessions/{id}/delete', DeleteTemplateController::class)->name('strength.templates.delete');

            // Schedule entries (a template placed on a day → planned → done).
            Route::post('/schedule/plan', ScheduleWorkoutController::class)->name('strength.schedule');
            Route::post('/schedule', LogStrengthSessionController::class)->name('strength.session.save');
            Route::get('/schedule/{id}', ShowSessionEditorController::class)->name('strength.session');
            Route::post('/schedule/{id}/delete', RemoveScheduledWorkoutController::class)->name('strength.session.delete');

            // Custom exercises (shared by every editor).
            Route::post('/exercises', AddCustomExerciseController::class)->name('strength.exercises.add');
        });
    }
}
