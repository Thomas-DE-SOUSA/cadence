<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\LogFood;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Strength\Domain\Enum\Meal;
use Cadence\Strength\Domain\Port\NutritionEntryRepository;
use Cadence\Strength\Domain\ValueObject\NutritionEntry;

/**
 * Records a meal description as a single "pending" entry WITHOUT calling the AI,
 * so the request returns instantly. The macros are filled in afterwards by
 * {@see EstimateFoodUseCase} (triggered client-side), which keeps the flaky/slow
 * estimator off the critical path — no timeouts, no 500s.
 */
final readonly class LogPendingFoodUseCase
{
    public function __construct(
        private NutritionEntryRepository $entries,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function execute(LogFoodInput $input, ExecutionContext $context): NutritionEntry
    {
        $date = $input->date ?? $this->clock->now()->format('Y-m-d');
        $entry = new NutritionEntry(
            $this->ids->generate(),
            $date,
            Meal::from($input->meal),
            mb_substr(trim($input->text), 0, 255),
            0,
            0,
            0,
            0,
            'pending',
        );
        $this->entries->save($context->tenant, $entry);

        return $entry;
    }
}
