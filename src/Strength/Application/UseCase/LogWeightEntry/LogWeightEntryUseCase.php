<?php

declare(strict_types=1);

namespace Cadence\Strength\Application\UseCase\LogWeightEntry;

use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Strength\Domain\Enum\WeighMoment;
use Cadence\Strength\Domain\Port\WeightEntryRepository;
use Cadence\Strength\Domain\ValueObject\WeightEntry;

/** Records (or replaces) one body-weight reading for the acting tenant. */
final readonly class LogWeightEntryUseCase
{
    public function __construct(
        private WeightEntryRepository $entries,
        private Clock $clock,
    ) {
    }

    public function execute(LogWeightEntryInput $input, ExecutionContext $context): void
    {
        $date = $input->date ?? $this->clock->now()->format('Y-m-d');

        $entry = new WeightEntry(
            $date,
            WeighMoment::from($input->moment),
            $input->weightKg,
            trim($input->note),
        );

        $this->entries->save($context->tenant, $entry);
    }
}
