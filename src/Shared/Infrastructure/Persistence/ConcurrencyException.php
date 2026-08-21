<?php

declare(strict_types=1);

namespace Cadence\Shared\Infrastructure\Persistence;

use RuntimeException;

/** Raised when an optimistic-lock update finds the aggregate was changed concurrently. */
final class ConcurrencyException extends RuntimeException
{
}
