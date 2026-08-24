<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Ai;

use Cadence\Shared\Infrastructure\Ai\GeminiClient;

/**
 * Streams the guest-advisory diagnostic from Google Gemini (free tier) over SSE,
 * forwarding each text delta. Free-form Markdown reply, no tools.
 */
final class AdvisorStreamer
{
    public function __construct(private readonly GeminiClient $client)
    {
    }

    /** @param callable(string):void $onText */
    public function stream(string $system, string $user, callable $onText): string
    {
        $result = $this->client->stream(
            $system,
            [['role' => 'user', 'parts' => [['text' => $user]]]],
            [],
            $onText,
        );

        return $result['text'];
    }
}
