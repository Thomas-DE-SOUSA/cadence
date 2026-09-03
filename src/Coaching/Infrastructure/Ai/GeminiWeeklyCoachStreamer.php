<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Ai;

use Cadence\Coaching\Domain\Port\WeeklyCoachStreamer;
use Cadence\Coaching\Domain\ValueObject\CoachReply;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewContext;
use Cadence\Shared\Infrastructure\Ai\GeminiClient;

/**
 * Streams the weekly cross-modal verdict from Google Gemini (free tier), over
 * SSE, forwarding each text delta. Free-form Markdown reply, no tools — the
 * verdict is advice; running/muscu changes are described in prose.
 */
final class GeminiWeeklyCoachStreamer implements WeeklyCoachStreamer
{
    public function __construct(
        private readonly GeminiClient $client,
        private readonly WeeklyCoachRequestBuilder $builder,
    ) {
    }

    public function stream(WeeklyReviewContext $context, array $history, callable $onText): CoachReply
    {
        $result = $this->client->stream(
            $this->builder->system($context),
            $this->builder->contents($history),
            [],
            $onText,
        );

        return new CoachReply($result['text'], null);
    }
}
