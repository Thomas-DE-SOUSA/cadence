<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Cadence\Shared\Identifier\IdGenerator;

/** Deterministic id generator for tests. Returns queued ids, then repeats the last. */
final class FixedIdGenerator implements IdGenerator
{
    private int $cursor = 0;

    /** @param list<string> $ids */
    public function __construct(
        private array $ids = ['01900000-0000-7000-8000-000000000001'],
    ) {
    }

    public function generate(): string
    {
        $id = $this->ids[$this->cursor] ?? $this->ids[array_key_last($this->ids)];
        $this->cursor++;

        return $id;
    }
}
