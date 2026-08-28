<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\LogStrengthSession;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Strength\Domain\Model\StrengthSession;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\ValueObject\PerformedExercise;

/** Records (or updates) a logged strength workout for the acting tenant. */
final readonly class LogStrengthSessionUseCase
{
    public function __construct(
        private StrengthSessionRepository $sessions,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function execute(LogStrengthSessionInput $input, ExecutionContext $context): string
    {
        $id = $input->id ?? $this->ids->generate();
        $date = trim($input->date) !== '' ? $input->date : $this->clock->now()->format('Y-m-d');

        $exercises = [];
        foreach ($input->exercises as $raw) {
            $exercises[] = PerformedExercise::fromArray($raw);
        }

        $existing = $input->id !== null ? $this->sessions->ofId($input->id, $context->tenant) : null;
        $version = $existing !== null ? ($existing->toSnapshot()['version'] + 1) : 1;

        $session = new StrengthSession(
            $id,
            $context->tenant->value,
            $date,
            trim($input->title),
            trim($input->note),
            $input->durationSeconds,
            $exercises,
            $version,
        );

        $this->sessions->save($session);

        return $id;
    }
}
