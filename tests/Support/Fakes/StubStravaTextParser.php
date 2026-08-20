<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Cadence\Activity\Application\Port\ParsedActivity;
use Cadence\Activity\Application\Port\StravaTextParser;

/** Returns a canned parse result — lets us test the import flow without calling an LLM. */
final class StubStravaTextParser implements StravaTextParser
{
    public function __construct(private readonly ParsedActivity $result)
    {
    }

    public function parse(string $rawText): ParsedActivity
    {
        return $this->result;
    }
}
