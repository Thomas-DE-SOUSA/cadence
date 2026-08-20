<?php

declare(strict_types=1);

namespace Cadence\Shared\Infrastructure\Persistence;

use RuntimeException;

/** Wraps a low-level persistence error so raw DB details never reach the caller. */
final class PersistenceFailure extends RuntimeException
{
}
