<?php

declare(strict_types=1);

namespace Cadence\Coaching\Application\UseCase\SubmitWellnessCheckIn;

use Cadence\Coaching\Domain\Port\WellnessCheckInRepository;
use Cadence\Coaching\Domain\ValueObject\WellnessCheckIn;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;

/** Records (or replaces) today's subjective check-in for the acting tenant. */
final readonly class SubmitWellnessCheckInUseCase
{
    public function __construct(
        private WellnessCheckInRepository $checkIns,
        private Clock $clock,
    ) {
    }

    public function execute(SubmitWellnessCheckInInput $input, ExecutionContext $context): void
    {
        $checkIn = new WellnessCheckIn(
            $this->clock->now()->format('Y-m-d'),
            $input->sleep,
            $input->energy,
            $input->legs,
            $input->motivation,
            $input->painLevel,
            trim($input->painLocation),
            trim($input->note),
        );

        $this->checkIns->save($context->tenant, $checkIn);
    }
}
