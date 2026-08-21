<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Application\CoachTurnService;
use Cadence\Coaching\Domain\Exception\DayContextMissing;
use Cadence\Coaching\Domain\Port\CoachStreamer;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Application\TenantContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Streams the coach's reply token by token over SSE, then persists the turn. */
final class StreamCoachController
{
    public function __construct(
        private readonly CoachTurnService $turns,
        private readonly CoachStreamer $streamer,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(Request $request, string $id): StreamedResponse
    {
        $data = $request->validate([
            'cycle_id' => ['required', 'string', 'max:64'],
            'date' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $context = new ExecutionContext($this->tenantContext->current());

        try {
            $turn = $this->turns->start($id, $data['cycle_id'], $data['date'], $data['message'], $context);
        } catch (DayContextMissing) {
            abort(404);
        }

        return response()->stream(function () use ($turn, $context): void {
            $emit = static function (string $event, array $payload): void {
                echo 'event: '.$event."\n".'data: '.(string) json_encode($payload)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            try {
                $reply = $this->streamer->stream(
                    $turn->context,
                    $turn->history,
                    static fn (string $delta) => $emit('text', ['t' => $delta]),
                );
                $this->turns->finish($turn->conversationId, $reply->text, $reply->proposal, $context);
                $emit('done', ['conversationId' => $turn->conversationId->value, 'hasProposal' => $reply->proposal !== null]);
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
