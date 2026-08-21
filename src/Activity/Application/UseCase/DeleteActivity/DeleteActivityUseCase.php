<?php

declare(strict_types=1);

namespace Cadence\Activity\Application\UseCase\DeleteActivity;

use Cadence\Activity\Domain\Exception\ActivityNotFound;
use Cadence\Activity\Domain\Port\ActivityRepository;
use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Shared\Application\AuditTrail;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Clock\Clock;

final readonly class DeleteActivityUseCase
{
    public function __construct(
        private ActivityRepository $activities,
        private AuditTrail $auditTrail,
        private Clock $clock,
    ) {
    }

    public function execute(string $activityId, ExecutionContext $context): void
    {
        $tenant = $context->tenant;
        $id = ActivityId::fromString($activityId);

        if ($this->activities->ofId($id, $tenant) === null) {
            throw ActivityNotFound::withId($id);
        }

        $this->activities->delete($id, $tenant);

        $this->auditTrail->record(
            'activity.deleted',
            $tenant,
            $id->value,
            ['id' => $id->value],
            $this->clock->now(),
        );
    }
}
