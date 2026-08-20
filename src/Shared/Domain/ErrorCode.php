<?php

declare(strict_types=1);

namespace Cadence\Shared\Domain;

/**
 * Marker implemented by each bounded context's backed string enum of error
 * codes. Codes are the FE<->BE contract source of truth; the code -> HTTP
 * status mapping lives in the framework exception handler, never in the domain.
 */
interface ErrorCode
{
}
