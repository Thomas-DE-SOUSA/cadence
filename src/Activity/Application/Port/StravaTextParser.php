<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\Port;

use Cadence\Activity\Application\Port\Exception\ActivityTextUnparseable;

/**
 * Turns raw pasted activity text (Strava splits, best efforts, totals) into a
 * structured {@see ParsedActivity}. Implemented in infrastructure (LLM adapter).
 */
interface StravaTextParser
{
    /** @throws ActivityTextUnparseable when the text cannot be turned into an activity. */
    public function parse(string $rawText): ParsedActivity;
}
