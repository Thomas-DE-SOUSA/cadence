<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\UpdateActivity;

use Cadence\Activity\Application\UseCase\RecordActivity\BestEffortInput;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;
use Cadence\Activity\Domain\Event\ActivityRevised;
use Cadence\Activity\Domain\Exception\ActivityNotFound;
use Cadence\Activity\Domain\Model\BestEffort;
use Cadence\Activity\Domain\Model\Split;
use Cadence\Activity\Domain\Port\ActivityRepository;
use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Activity\Domain\ValueObject\Distance;
use Cadence\Activity\Domain\ValueObject\Duration;
use Cadence\Activity\Domain\ValueObject\Elevation;
use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\EventPublisher;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;
use DateTimeImmutable;

/** Revises an existing activity's summary fields. */
final readonly class UpdateActivityUseCase
{
    public function __construct(
        private ActivityRepository $activities,
        private Clock $clock,
        private EventPublisher $eventPublisher,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(UpdateActivityInput $input, ExecutionContext $context): void
    {
        $tenant = $context->tenant;
        $id = ActivityId::fromString($input->activityId);

        $activity = $this->activities->ofId($id, $tenant);
        if ($activity === null) {
            throw ActivityNotFound::withId($id);
        }

        $revised = $activity->revise(
            new DateTimeImmutable($input->occurredAt),
            Distance::fromMeters($input->distanceMeters),
            Duration::fromSeconds($input->movingSeconds),
            Duration::fromSeconds($input->elapsedSeconds),
            Elevation::ofMeters($input->elevationGainMeters),
            array_map(
                static fn (SplitInput $s): Split => Split::record(
                    $s->index,
                    Distance::fromMeters($s->distanceMeters),
                    Duration::fromSeconds($s->durationSeconds),
                    Elevation::ofMeters($s->elevationMeters),
                ),
                $input->splits,
            ),
            array_map(
                static fn (BestEffortInput $b): BestEffort => BestEffort::record(
                    $b->label,
                    Distance::fromMeters($b->distanceMeters),
                    Duration::fromSeconds($b->durationSeconds),
                    $b->isPersonalRecord,
                ),
                $input->bestEfforts,
            ),
            $this->clock->now(),
        );

        $events = $revised->releaseEvents();
        $this->activities->save($revised, $events);
        $this->eventPublisher->publish($events);
        $this->auditTrail->record(
            ActivityRevised::NAME,
            $tenant,
            $id->value,
            $revised->toSnapshot(),
            $this->clock->now(),
        );
    }
}
