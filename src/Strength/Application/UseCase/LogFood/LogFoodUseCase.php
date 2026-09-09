<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\LogFood;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Strength\Application\Port\FoodEstimator;
use Cadence\Strength\Domain\Enum\Meal;
use Cadence\Strength\Domain\Port\NutritionEntryRepository;
use Cadence\Strength\Domain\ValueObject\NutritionEntry;

/**
 * Estimates the macros of a free-text meal description (via the AI estimator)
 * and stores one nutrition entry per recognised food item for the acting tenant.
 */
final readonly class LogFoodUseCase
{
    public function __construct(
        private FoodEstimator $estimator,
        private NutritionEntryRepository $entries,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    /**
     * @return list<NutritionEntry> the entries that were saved (empty if nothing recognised)
     */
    public function execute(LogFoodInput $input, ExecutionContext $context): array
    {
        $date = $input->date ?? $this->clock->now()->format('Y-m-d');
        $meal = Meal::from($input->meal);

        $saved = [];
        foreach ($this->estimator->estimate($input->text) as $food) {
            $entry = new NutritionEntry(
                $this->ids->generate(),
                $date,
                $meal,
                $food->name,
                $food->kcal,
                $food->proteinG,
                $food->fatG,
                $food->carbsG,
            );
            $this->entries->save($context->tenant, $entry);
            $saved[] = $entry;
        }

        return $saved;
    }
}
