<?php

declare(strict_types=1);

namespace Cadence\Shared\Identifier;

/** Injected wherever an identity is minted. Business code never calls Str::uuid(). */
interface IdGenerator
{
    /** Time-ordered UUID (v7) as a string. */
    public function generate(): string;
}
