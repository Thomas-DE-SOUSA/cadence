<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\ValueObject;

use InvalidArgumentException;

/**
 * A day's subjective self-assessment: how the athlete slept, felt and whether
 * anything hurts. Feeds the readiness verdict and the coaching brain, so the
 * plan reacts to sensations — not just to load numbers.
 *
 * Sensations are 1..5 where 5 is best (great sleep / full energy / fresh legs /
 * highly motivated). Pain is 0..3 (0 none, 1 niggle, 2 moderate, 3 limits running).
 */
final readonly class WellnessCheckIn
{
    public function __construct(
        public string $date,          // Y-m-d
        public int $sleep,
        public int $energy,
        public int $legs,
        public int $motivation,
        public int $painLevel = 0,
        public string $painLocation = '',
        public string $note = '',
    ) {
        foreach (['sleep' => $sleep, 'energy' => $energy, 'legs' => $legs, 'motivation' => $motivation] as $name => $value) {
            if ($value < 1 || $value > 5) {
                throw new InvalidArgumentException(sprintf('%s must be between 1 and 5, got %d.', $name, $value));
            }
        }

        if ($painLevel < 0 || $painLevel > 3) {
            throw new InvalidArgumentException(sprintf('painLevel must be between 0 and 3, got %d.', $painLevel));
        }
    }

    /** A pain bad enough that today's running should be curtailed. */
    public function limitsRunning(): bool
    {
        return $this->painLevel >= 3;
    }
}
