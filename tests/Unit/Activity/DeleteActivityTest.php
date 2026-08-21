<?php

declare(strict_types=1);

use Cadence\Activity\Application\UseCase\DeleteActivity\DeleteActivityUseCase;
use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityInput;
use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityUseCase;
use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Domain\Exception\ActivityNotFound;
use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\FixedIdGenerator;
use Tests\Support\Fakes\InMemoryActivityRepository;
use Tests\Support\Fakes\RecordingAuditTrail;
use Tests\Support\Fakes\RecordingEventPublisher;

const DEL_ID = '01900000-0000-7000-8000-0000000000d1';

beforeEach(function (): void {
    $this->repository = new InMemoryActivityRepository();
    $this->audit = new RecordingAuditTrail();
    $this->context = new ExecutionContext(TenantId::fromString('tenant-thomas'));
    $clock = FixedClock::at('2026-08-21T09:00:00+00:00');

    (new RecordActivityUseCase(
        $this->repository,
        new FixedIdGenerator([DEL_ID]),
        $clock,
        new RecordingEventPublisher(),
        $this->audit,
    ))->execute(
        new RecordActivityInput('2026-08-19T18:00:00+00:00', ActivitySource::MANUAL->value, 10010, 2555, 2653, 32),
        $this->context,
    );

    $this->useCase = new DeleteActivityUseCase($this->repository, $this->audit, $clock);
});

describe('Feature: Deleting an activity', function (): void {
    it('deletes an existing activity and audits it', function (): void {
        $this->useCase->execute(DEL_ID, $this->context);

        $stored = $this->repository->ofId(ActivityId::fromString(DEL_ID), TenantId::fromString('tenant-thomas'));
        $deletedAudits = array_filter(
            $this->audit->entries,
            static fn (array $e): bool => $e['action'] === 'activity.deleted',
        );

        expect($stored)->toBeNull()
            ->and($deletedAudits)->not->toBeEmpty();
    });

    it('fails when the activity does not exist', function (): void {
        $call = fn () => $this->useCase->execute('unknown-id', $this->context);

        expect($call)->toThrow(ActivityNotFound::class);
    });
});
