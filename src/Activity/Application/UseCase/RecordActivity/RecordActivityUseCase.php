<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\RecordActivity;

use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Domain\Event\ActivityRecorded;
use Cadence\Activity\Domain\Exception\DuplicateActivity;
use Cadence\Activity\Domain\Model\Activity;
use Cadence\Activity\Domain\Service\SimilarActivityPolicy;
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
use Cadence\Shared\Identifier\IdGenerator;
use DateTimeImmutable;

/**
 * Records a run. Pure orchestrator: it reconstructs value objects, delegates
 * the decision to the aggregate, persists atomically, then publishes and audits.
 */
final readonly class RecordActivityUseCase
{
    public function __construct(
        private ActivityRepository $activities,
        private IdGenerator $ids,
        private Clock $clock,
        private EventPublisher $eventPublisher,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(RecordActivityInput $input, ExecutionContext $context): RecordActivityOutput
    {
        $tenant = $context->tenant;

        [$minDistance, $maxDistance] = SimilarActivityPolicy::range($input->distanceMeters);
        [$minMoving, $maxMoving] = SimilarActivityPolicy::range($input->movingSeconds);
        $day = SimilarActivityPolicy::day($input->occurredAt);

        if ($this->activities->hasActivityOn($tenant, $day, $minDistance, $maxDistance, $minMoving, $maxMoving)) {
            throw DuplicateActivity::onDay($day);
        }

        $id = ActivityId::generate($this->ids);

        $activity = Activity::record(
            $id,
            $tenant,
            new DateTimeImmutable($input->occurredAt),
            ActivitySource::from($input->source),
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

        $events = $activity->releaseEvents();
        $this->activities->save($activity, $events);
        // Durability is the outbox row written inside save(); this inline publish is
        // an interim best-effort delivery until the PublishOutboxJob drain lands.
        $this->eventPublisher->publish($events);
        $this->auditTrail->record(
            ActivityRecorded::NAME,
            $tenant,
            $id->value,
            $activity->toSnapshot(),
            $this->clock->now(),
        );

        return new RecordActivityOutput($id->value, $activity->averagePace()->secondsPerKm);
    }
}
