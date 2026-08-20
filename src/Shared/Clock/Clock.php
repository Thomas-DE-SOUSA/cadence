<?php

declare(strict_types=1);

namespace Cadence\Shared\Clock;

use DateTimeImmutable;

/** Injected everywhere the current time is needed. Business code never calls now(). */
interface Clock
{
    public function now(): DateTimeImmutable;
}
