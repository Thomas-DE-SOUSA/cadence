<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\Port;

use Cadence\Activity\Application\Port\Exception\ActivityTextUnparseable;

/**
 * Turns a photo/screenshot of a watch or app summary into a structured
 * {@see ParsedActivity}. Implemented in infrastructure (vision LLM adapter).
 */
interface ActivityPhotoParser
{
    /** @throws ActivityTextUnparseable when the image cannot be turned into an activity. */
    public function parse(string $imageBytes, string $mimeType): ParsedActivity;
}
