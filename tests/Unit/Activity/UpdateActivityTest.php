<?php

declare(strict_types=1);

use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityInput;
use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityUseCase;
use Cadence\Activity\Application\UseCase\UpdateActivity\UpdateActivityInput;
use Cadence\Activity\Application\UseCase\UpdateActivity\UpdateActivityUseCase;
use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Domain\Event\ActivityRevised;
use Cadence\Activity\Domain\Exception\ActivityNotFound;
use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\FixedIdGenerator;
use Tests\Support\Fakes\InMemoryActivityRepository;
use Tests\Support\Fakes\RecordingAuditTrail;
use Tests\Support\Fakes\RecordingEventPublisher;

const UPD_ID = '01900000-0000-7000-8000-0000000000e1';

beforeEach(function (): void {
    $this->repository = new InMemoryActivityRepository();
    $this->publisher = new RecordingEventPublisher();
    $this->audit = new RecordingAuditTrail();
    $this->context = new ExecutionContext(TenantId::fromString('tenant-thomas'));
    $clock = FixedClock::at('2026-08-21T09:00:00+00:00');

    (new RecordActivityUseCase(
        $this->repository,
        new FixedIdGenerator([UPD_ID]),
        $clock,
        $this->publisher,
        $this->audit,
    ))->execute(
        new RecordActivityInput('2026-08-19T18:00:00+00:00', ActivitySource::MANUAL->value, 10010, 2555, 2653, 32),
        $this->context,
    );

    $this->useCase = new UpdateActivityUseCase($this->repository, $clock, $this->publisher, $this->audit);
});

describe('Feature: Updating an activity', function (): void {
    it('revises the summary, bumps the version and records the revision', function (): void {
        $this->useCase->execute(
            new UpdateActivityInput(UPD_ID, '2026-08-20T10:00:00+00:00', 10500, 2600, 2700, 40),
            $this->context,
        );

        $revised = $this->repository->ofId(ActivityId::fromString(UPD_ID), TenantId::fromString('tenant-thomas'));

        expect($revised)->not->toBeNull()
            ->and($revised->version())->toBe(2)
            ->and($this->repository->outbox)->toHaveCount(2)
            ->and($this->repository->outbox[1])->toBeInstanceOf(ActivityRevised::class);
    });

    it('fails when the activity does not exist', function (): void {
        $call = fn () => $this->useCase->execute(
            new UpdateActivityInput('unknown-id', '2026-08-20T10:00:00+00:00', 10500, 2600, 2700, 40),
            $this->context,
        );

        expect($call)->toThrow(ActivityNotFound::class);
    });
});
