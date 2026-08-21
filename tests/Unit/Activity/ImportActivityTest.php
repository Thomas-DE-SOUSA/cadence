<?php

declare(strict_types=1);

use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityInput;
use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityUseCase;
use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Domain\Event\ActivityImported;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\FixedIdGenerator;
use Tests\Support\Fakes\InMemoryActivityRepository;
use Tests\Support\Fakes\RecordingAuditTrail;
use Tests\Support\Fakes\RecordingEventPublisher;

const IMPORT_TENANT = 'tenant-thomas';

beforeEach(function (): void {
    $this->repository = new InMemoryActivityRepository();
    $this->publisher = new RecordingEventPublisher();
    $this->audit = new RecordingAuditTrail();
    $this->context = new ExecutionContext(TenantId::fromString(IMPORT_TENANT));
    $this->useCase = new ImportActivityUseCase(
        $this->repository,
        new FixedIdGenerator(['01900000-0000-7000-8000-0000000000bb']),
        FixedClock::at('2026-08-21T09:00:00+00:00'),
        $this->publisher,
        $this->audit,
    );
});

function stravaImport(string $externalId = 'strava-12345'): ImportActivityInput
{
    return new ImportActivityInput(
        source: ActivitySource::STRAVA->value,
        externalId: $externalId,
        occurredAt: '2026-08-19T18:00:00+00:00',
        distanceMeters: 10010,
        movingSeconds: 2555,
        elapsedSeconds: 2653,
        elevationGainMeters: 32,
    );
}

describe('Feature: Importing an activity', function (): void {
    it('imports a provider activity and emits activity.imported', function (): void {
        $output = $this->useCase->execute(stravaImport(), $this->context);

        expect($output->imported)->toBeTrue()
            ->and($output->activityId)->toBe('01900000-0000-7000-8000-0000000000bb')
            ->and($this->repository->outbox)->toHaveCount(1)
            ->and($this->repository->outbox[0])->toBeInstanceOf(ActivityImported::class)
            ->and($this->audit->entries[0]['action'])->toBe('activity.imported');
    });

    it('skips an activity already imported for the same external id', function (): void {
        $this->useCase->execute(stravaImport('strava-777'), $this->context);
        $second = $this->useCase->execute(stravaImport('strava-777'), $this->context);

        expect($second->imported)->toBeFalse()
            ->and($second->activityId)->toBeNull()
            ->and($this->repository->outbox)->toHaveCount(1);
    });

    it('rejects importing a run similar to one already present (any source)', function (): void {
        $this->useCase->execute(stravaImport('strava-A'), $this->context);

        $call = fn () => $this->useCase->execute(stravaImport('strava-B'), $this->context);

        expect($call)->toThrow(Cadence\Activity\Domain\Exception\DuplicateActivity::class);
    });
});
