<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\Port\Exception;

use RuntimeException;

/** Raised when pasted text cannot be parsed into an activity (bad input or parser failure). */
final class ActivityTextUnparseable extends RuntimeException
{
}
