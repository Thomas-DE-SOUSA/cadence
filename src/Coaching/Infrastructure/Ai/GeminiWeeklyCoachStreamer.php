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
            $this->toContents($this->builder->messages($history)),
            [],
            $onText,
        );

        return new CoachReply($result['text'], null);
    }

    /**
     * Maps the builder's {role,content} messages to Gemini's contents shape.
     * With no history yet, seeds a single user turn so the model produces the
     * opening verdict.
     *
     * @param list<array{role:string,content:string}> $messages
     *
     * @return list<array{role:string,parts:list<array{text:string}>}>
     */
    private function toContents(array $messages): array
    {
        $contents = [];
        foreach ($messages as $message) {
            $contents[] = [
                'role' => $message['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $message['content']]],
            ];
        }

        if ($contents === []) {
            $contents[] = ['role' => 'user', 'parts' => [['text' => 'Fais le bilan de ma semaine.']]];
        }

        return $contents;
    }
}
