<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Knowledge;

/** Loads the coaching doctrine and method notes that ground the coach. */
final class CoachingKnowledge
{
    public function systemFoundation(): string
    {
        $parts = [$this->read(__DIR__.'/doctrine.md')];

        foreach (glob(__DIR__.'/methods/*.md') ?: [] as $file) {
            $parts[] = $this->read($file);
        }

        return implode("\n\n---\n\n", array_filter($parts));
    }

    private function read(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : '';

        return $contents === false ? '' : trim($contents);
    }
}
