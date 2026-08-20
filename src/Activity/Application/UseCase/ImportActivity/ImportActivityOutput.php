<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\ImportActivity;

final readonly class ImportActivityOutput
{
    public function __construct(
        public ?string $activityId,
        public bool $imported,
    ) {
    }

    public static function skipped(): self
    {
        return new self(null, false);
    }
}
