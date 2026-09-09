<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\RemoveNutritionEntry;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Strength\Domain\Port\NutritionEntryRepository;

/** Removes one logged food item (tenant-scoped). */
final readonly class RemoveNutritionEntryUseCase
{
    public function __construct(private NutritionEntryRepository $entries)
    {
    }

    public function execute(string $id, ExecutionContext $context): void
    {
        $this->entries->delete($context->tenant, $id);
    }
}
