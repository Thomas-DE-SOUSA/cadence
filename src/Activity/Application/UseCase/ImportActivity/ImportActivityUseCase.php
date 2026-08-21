<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\ImportActivity;

use Cadence\Activity\Application\UseCase\RecordActivity\BestEffortInput;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;
use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Domain\Event\ActivityImported;
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
 * Imports one activity from an external provider. Idempotent: an activity
 * already imported for the same (tenant, source, external id) is skipped.
 */
final readonly class ImportActivityUseCase
{
    public function __construct(
        private ActivityRepository $activities,
        private IdGenerator $ids,
        private Clock $clock,
        private EventPublisher $eventPublisher,
        private AuditTrail $auditTrail,
    ) {
    }

    public function execute(ImportActivityInput $input, ExecutionContext $context): ImportActivityOutput
    {
        $tenant = $context->tenant;
        $source = ActivitySource::from($input->source);

        if ($this->activities->existsForExternalId($tenant, $source, $input->externalId)) {
            return ImportActivityOutput::skipped();
        }

        [$minDistance, $maxDistance] = SimilarActivityPolicy::range($input->distanceMeters);
        [$minMoving, $maxMoving] = SimilarActivityPolicy::range($input->movingSeconds);
        $day = SimilarActivityPolicy::day($input->occurredAt);

        if ($this->activities->hasActivityOn($tenant, $day, $minDistance, $maxDistance, $minMoving, $maxMoving)) {
            throw DuplicateActivity::onDay($day);
        }

        $id = ActivityId::generate($this->ids);

        $activity = Activity::import(
            $id,
            $tenant,
            new DateTimeImmutable($input->occurredAt),
            $source,
            $input->externalId,
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
        $this->eventPublisher->publish($events);
        $this->auditTrail->record(
            ActivityImported::NAME,
            $tenant,
            $id->value,
            $activity->toSnapshot(),
            $this->clock->now(),
        );

        return new ImportActivityOutput($id->value, true);
    }
}
