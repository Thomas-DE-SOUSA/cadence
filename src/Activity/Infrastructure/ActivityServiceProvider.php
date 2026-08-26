<?php

declare(strict_types=1);

namespace Cadence\Activity\Infrastructure;

use Cadence\Activity\Application\Port\ActivityPhotoParser;
use Cadence\Activity\Application\Port\StravaTextParser;
use Cadence\Activity\Domain\Port\ActivityRepository;
use Cadence\Activity\Infrastructure\Ai\GeminiActivityPhotoParser;
use Cadence\Activity\Infrastructure\Ai\GeminiStravaTextParser;
use Cadence\Activity\Infrastructure\Http\Controller\RecordActivityController;
use Cadence\Activity\Infrastructure\Persistence\Eloquent\EloquentActivityRepository;
use Cadence\Shared\Infrastructure\Ai\GeminiClient;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ActivityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivityRepository::class, EloquentActivityRepository::class);
        $this->app->bind(
            StravaTextParser::class,
            fn (): GeminiStravaTextParser => new GeminiStravaTextParser(
                new GeminiClient((string) config('services.gemini.key', ''), (string) config('services.gemini.model')),
            ),
        );
        $this->app->bind(
            ActivityPhotoParser::class,
            fn (): GeminiActivityPhotoParser => new GeminiActivityPhotoParser(
                new GeminiClient((string) config('services.gemini.key', ''), (string) config('services.gemini.model')),
            ),
        );
    }

    public function boot(): void
    {
        // JSON endpoints under /api — no web/session/CSRF middleware.
        Route::prefix('api')->group(function (): void {
            Route::post('/activities', RecordActivityController::class)->name('api.activities.store');
        });
    }
}
