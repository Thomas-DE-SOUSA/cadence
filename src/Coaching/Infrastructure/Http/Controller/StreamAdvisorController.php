<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Infrastructure\Ai\AdvisorPromptBuilder;
use Cadence\Coaching\Infrastructure\Ai\AdvisorStreamer;
use Cadence\Coaching\Infrastructure\Read\GuestAssessment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Streams the guest diagnostic (Markdown) over SSE from the questionnaire + measured efforts. */
final class StreamAdvisorController
{
    public function __construct(
        private readonly AdvisorPromptBuilder $builder,
        private readonly AdvisorStreamer $streamer,
    ) {
    }

    public function __invoke(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'profile' => ['array'],
            'efforts' => ['array'],
            'efforts.*.distanceMeters' => ['integer', 'min:100'],
            'efforts.*.seconds' => ['integer', 'min:1'],
            'chronos' => ['array'],
            'chronos.*.distanceMeters' => ['integer', 'min:100'],
            'chronos.*.seconds' => ['integer', 'min:1'],
        ]);

        $efforts = is_array($data['efforts'] ?? null) ? $data['efforts'] : [];
        $chronos = is_array($data['chronos'] ?? null) ? $data['chronos'] : [];
        /** @var array<int, int> $best */
        $best = [];
        foreach ([...$efforts, ...$chronos] as $effort) {
            if (! is_array($effort)) {
                continue;
            }
            $distance = (int) ($effort['distanceMeters'] ?? 0);
            $seconds = (int) ($effort['seconds'] ?? 0);
            if ($distance > 0 && $seconds > 0 && (! isset($best[$distance]) || $seconds < $best[$distance])) {
                $best[$distance] = $seconds;
            }
        }

        $assessment = GuestAssessment::assess($best);
        /** @var array<string, mixed> $profile */
        $profile = is_array($data['profile'] ?? null) ? $data['profile'] : [];
        $system = $this->builder->system();
        $user = $this->builder->user($profile, $assessment);

        return response()->stream(function () use ($system, $user): void {
            $emit = static function (string $event, array $payload): void {
                echo 'event: '.$event."\n".'data: '.(string) json_encode($payload)."\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            try {
                $this->streamer->stream($system, $user, static fn (string $delta) => $emit('text', ['t' => $delta]));
                $emit('done', []);
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
