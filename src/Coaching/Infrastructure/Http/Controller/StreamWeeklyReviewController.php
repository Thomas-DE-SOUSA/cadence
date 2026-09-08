<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Application\WeeklyReview\WeekAnchor;
use Cadence\Coaching\Application\WeeklyReview\WeeklyReviewService;
use Cadence\Coaching\Domain\Port\WeeklyCoachStreamer;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Streams the weekly cross-modal verdict token by token over SSE, then persists the turn. */
final class StreamWeeklyReviewController
{
    public function __construct(
        private readonly WeeklyReviewService $reviews,
        private readonly WeeklyCoachStreamer $streamer,
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'week_start' => ['nullable', 'date_format:Y-m-d'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $weekStart = WeekAnchor::monday($data['week_start'] ?? null, $this->clock);
        $message = (string) $data['message'];
        $context = new ExecutionContext($this->tenantContext->current());

        return response()->stream(function () use ($weekStart, $message, $context): void {
            $emit = static function (string $event, array $payload): void {
                echo 'event: '.$event."\n".'data: '.(string) json_encode($payload)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            try {
                // Assembling the context can fail (a provider/DB error); do it here
                // so it surfaces as `event: error`, not a raw 500 with no SSE frame.
                $turn = $this->reviews->start($weekStart, $message, $context);
                $reply = $this->streamer->stream(
                    $turn->context,
                    $turn->history,
                    static fn (string $delta) => $emit('text', ['t' => $delta]),
                );
                $this->reviews->finish($turn->reviewId, $reply->text, $context);
                $emit('done', ['reviewId' => $turn->reviewId->value]);
            } catch (Throwable $e) {
                $emit('error', ['message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }
}
