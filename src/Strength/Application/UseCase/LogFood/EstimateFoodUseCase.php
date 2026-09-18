<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\LogFood;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Identifier\IdGenerator;
use Cadence\Strength\Application\Port\Exception\FoodEstimationFailed;
use Cadence\Strength\Application\Port\FoodEstimator;
use Cadence\Strength\Domain\Port\NutritionEntryRepository;
use Cadence\Strength\Domain\ValueObject\NutritionEntry;

/**
 * Second half of async food logging: estimate a pending entry's macros with the
 * AI, then replace it with one done entry per recognised food. Estimation
 * errors (or nothing recognised) mark the entry "failed" instead of blowing up,
 * so the caller stays simple and the user can retry.
 */
final readonly class EstimateFoodUseCase
{
    public function __construct(
        private FoodEstimator $estimator,
        private NutritionEntryRepository $entries,
        private IdGenerator $ids,
    ) {
    }

    /**
     * @return list<NutritionEntry> the estimated entries (empty on failure / nothing recognised)
     */
    public function execute(string $entryId, ExecutionContext $context): array
    {
        $pending = $this->entries->find($context->tenant, $entryId);
        if ($pending === null || ! in_array($pending->status, ['pending', 'failed'], true)) {
            return []; // already estimated (done), removed, or not ours
        }

        try {
            $foods = $this->estimator->estimate($pending->description);
        } catch (FoodEstimationFailed) {
            $this->entries->save($context->tenant, $this->withStatus($pending, 'failed'));

            return [];
        }

        if ($foods === []) {
            $this->entries->save($context->tenant, $this->withStatus($pending, 'failed'));

            return [];
        }

        // Replace the single pending row with one done row per recognised food.
        $this->entries->delete($context->tenant, $pending->id);

        $saved = [];
        foreach ($foods as $food) {
            $entry = new NutritionEntry(
                $this->ids->generate(),
                $pending->date,
                $pending->meal,
                $food->name,
                $food->kcal,
                $food->proteinG,
                $food->fatG,
                $food->carbsG,
                'done',
            );
            $this->entries->save($context->tenant, $entry);
            $saved[] = $entry;
        }

        return $saved;
    }

    private function withStatus(NutritionEntry $e, string $status): NutritionEntry
    {
        return new NutritionEntry($e->id, $e->date, $e->meal, $e->description, $e->kcal, $e->proteinG, $e->fatG, $e->carbsG, $status);
    }
}
