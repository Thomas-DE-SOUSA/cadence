<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure;

use Cadence\Coaching\Domain\Port\AthleteHistoryProvider;
use Cadence\Coaching\Domain\Port\CoachChat;
use Cadence\Coaching\Domain\Port\CoachStreamer;
use Cadence\Coaching\Domain\Port\ConversationRepository;
use Cadence\Coaching\Domain\Port\ProgramContextProvider;
use Cadence\Coaching\Domain\Port\SessionAdjuster;
use Cadence\Coaching\Infrastructure\Ai\AdvisorStreamer;
use Cadence\Coaching\Infrastructure\Ai\ClaudeCoachChat;
use Cadence\Coaching\Infrastructure\Ai\ClaudeCoachStreamer;
use Cadence\Coaching\Infrastructure\Ai\CoachRequestBuilder;
use Cadence\Coaching\Infrastructure\Http\Controller\AnalyzeGuestGpxController;
use Cadence\Coaching\Infrastructure\Http\Controller\ApplyProposalController;
use Cadence\Coaching\Infrastructure\Http\Controller\SendCoachMessageController;
use Cadence\Coaching\Infrastructure\Http\Controller\ShowCoachThreadController;
use Cadence\Coaching\Infrastructure\Http\Controller\StreamAdvisorController;
use Cadence\Coaching\Infrastructure\Http\Controller\StreamCoachController;
use Cadence\Coaching\Infrastructure\Knowledge\CoachingKnowledge;
use Inertia\Inertia;
use Cadence\Coaching\Infrastructure\Persistence\Eloquent\EloquentConversationRepository;
use Cadence\Coaching\Infrastructure\Provider\EloquentAthleteHistoryProvider;
use Cadence\Coaching\Infrastructure\Provider\TrainingProgramContextProvider;
use Cadence\Coaching\Infrastructure\Provider\TrainingSessionAdjuster;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CoachingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AthleteHistoryProvider::class, EloquentAthleteHistoryProvider::class);
        $this->app->bind(ConversationRepository::class, EloquentConversationRepository::class);
        $this->app->bind(ProgramContextProvider::class, TrainingProgramContextProvider::class);
        $this->app->bind(SessionAdjuster::class, TrainingSessionAdjuster::class);

        $builder = fn (): CoachRequestBuilder => new CoachRequestBuilder(new CoachingKnowledge());
        $this->app->bind(CoachChat::class, fn (): ClaudeCoachChat => new ClaudeCoachChat(
            (string) config('services.anthropic.key'),
            $builder(),
        ));
        $this->app->bind(CoachStreamer::class, fn (): ClaudeCoachStreamer => new ClaudeCoachStreamer(
            (string) config('services.anthropic.key'),
            $builder(),
        ));
        $this->app->bind(AdvisorStreamer::class, fn (): AdvisorStreamer => new AdvisorStreamer(
            (string) config('services.anthropic.key'),
        ));
    }

    public function boot(): void
    {
        Route::prefix('programme/{id}/coach')->group(function (): void {
            Route::get('/thread', ShowCoachThreadController::class)->name('programs.coach.thread');
            Route::post('/message', SendCoachMessageController::class)->name('programs.coach.message');
            Route::post('/stream', StreamCoachController::class)->name('programs.coach.stream');
            Route::post('/apply', ApplyProposalController::class)->name('programs.coach.apply');
        });

        // Guest advisory tool ("Conseil") — assess any runner, no persistence.
        Route::middleware('web')->prefix('conseil')->group(function (): void {
            Route::get('/', fn () => Inertia::render('Advisor'))->name('advisor');
            Route::post('/analyser-gpx', AnalyzeGuestGpxController::class)->name('advisor.analyze');
            Route::post('/diagnostic', StreamAdvisorController::class)->name('advisor.diagnostic');
        });
    }
}
