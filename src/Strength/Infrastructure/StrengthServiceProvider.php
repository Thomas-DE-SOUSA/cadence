<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure;

use Cadence\Shared\Infrastructure\Ai\GeminiClient;
use Cadence\Strength\Application\Port\FoodEstimator;
use Cadence\Strength\Domain\Port\ExerciseRepository;
use Cadence\Strength\Domain\Port\MuscuProfileRepository;
use Cadence\Strength\Domain\Port\NutritionEntryRepository;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Port\WeightEntryRepository;
use Cadence\Strength\Domain\Port\WorkoutTemplateRepository;
use Cadence\Strength\Infrastructure\Ai\GeminiFoodEstimator;
use Cadence\Strength\Infrastructure\Http\Controller\AddCustomExerciseController;
use Cadence\Strength\Infrastructure\Http\Controller\DeleteNutritionEntryController;
use Cadence\Strength\Infrastructure\Http\Controller\LogNutritionController;
use Cadence\Strength\Infrastructure\Http\Controller\SaveMuscuProfileController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowMuscuProfileController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowNutritionController;
use Cadence\Strength\Infrastructure\Http\Controller\DeleteTemplateController;
use Cadence\Strength\Infrastructure\Http\Controller\LogStrengthSessionController;
use Cadence\Strength\Infrastructure\Http\Controller\LogWeightEntryController;
use Cadence\Strength\Infrastructure\Http\Controller\RemoveScheduledWorkoutController;
use Cadence\Strength\Infrastructure\Http\Controller\SaveTemplateController;
use Cadence\Strength\Infrastructure\Http\Controller\ScheduleWorkoutController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowAgendaController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowProgressionController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowSessionEditorController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowTemplateEditorController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowTemplatesController;
use Cadence\Strength\Infrastructure\Http\Controller\ShowWeightController;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\EloquentExerciseRepository;
use Cadence\Strength\Infrastructure\Persistence\Eloquent\EloquentMuscuProfileRepository;
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
        $this->app->bind(MuscuProfileRepository::class, EloquentMuscuProfileRepository::class);
        $this->app->bind(WeightEntryRepository::class, EloquentWeightEntryRepository::class);
        $this->app->bind(NutritionEntryRepository::class, EloquentNutritionEntryRepository::class);
        $this->app->bind(FoodEstimator::class, fn (): GeminiFoodEstimator => new GeminiFoodEstimator(
            new GeminiClient(
                (string) config('services.gemini.key', ''),
                (string) config('services.gemini.model'),
                ['gemini-3.7-flash', 'gemini-3.8-flash'],
            ),
        ));
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth'])->prefix('muscu')->group(function (): void {
            // Agenda (home) + progression.
            Route::get('/', ShowAgendaController::class)->name('muscu');
            Route::get('/progression', ShowProgressionController::class)->name('muscu.progression');

            // Body-weight tracker (morning/evening readings → weekly averages).
            Route::get('/poids', ShowWeightController::class)->name('muscu.weight');
            Route::post('/poids', LogWeightEntryController::class)->name('muscu.weight.save');

            // Nutrition: daily food log (AI-estimated) vs. lean-bulk targets.
            Route::get('/nutrition', ShowNutritionController::class)->name('muscu.nutrition');
            Route::post('/nutrition', LogNutritionController::class)->name('muscu.nutrition.log');
            Route::post('/nutrition/{id}/supprimer', DeleteNutritionEntryController::class)->name('muscu.nutrition.delete');

            // Muscu profile (goal, level, equipment, priorities…).
            Route::get('/profil', ShowMuscuProfileController::class)->name('muscu.profile');
            Route::post('/profil', SaveMuscuProfileController::class)->name('muscu.profile.save');

            // Séance templates (the reusable library).
            Route::get('/seances', ShowTemplatesController::class)->name('muscu.templates');
            Route::get('/seances/nouveau', ShowTemplateEditorController::class)->name('muscu.templates.new');
            Route::post('/seances', SaveTemplateController::class)->name('muscu.templates.save');
            Route::get('/seances/{id}/modifier', ShowTemplateEditorController::class)->name('muscu.templates.edit');
            Route::post('/seances/{id}/supprimer', DeleteTemplateController::class)->name('muscu.templates.delete');

            // Agenda entries (a template placed on a day → planned → done).
            Route::post('/agenda/planifier', ScheduleWorkoutController::class)->name('muscu.schedule');
            Route::post('/agenda', LogStrengthSessionController::class)->name('muscu.session.save');
            Route::get('/agenda/{id}', ShowSessionEditorController::class)->name('muscu.session');
            Route::post('/agenda/{id}/supprimer', RemoveScheduledWorkoutController::class)->name('muscu.session.delete');

            // Custom exercises (shared by every editor).
            Route::post('/exercices', AddCustomExerciseController::class)->name('muscu.exercises.add');
        });
    }
}
