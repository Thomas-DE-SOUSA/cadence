<?php

declare(strict_types=1);

namespace Cadence\Shared\Domain;

use BackedEnum;
use RuntimeException;

/**
 * Base class for typed business exceptions. Carries an ErrorCode (a backed
 * enum) — never an HTTP status. The exception handler maps the code to a
 * status at the edge.
 */
abstract class DomainException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode&BackedEnum $errorCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : (string) $this->errorCode->value);
    }

    public function code(): string
    {
        return (string) $this->errorCode->value;
    }
}
