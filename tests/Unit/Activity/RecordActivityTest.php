<?php

declare(strict_types=1);

use Cadence\Activity\Application\UseCase\RecordActivity\BestEffortInput;
use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityInput;
use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityUseCase;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;
use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Domain\Event\ActivityRecorded;
use Cadence\Activity\Domain\Exception\ActivityErrorCode;
use Cadence\Activity\Domain\Exception\InvalidActivity;
use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\FixedIdGenerator;
use Tests\Support\Fakes\InMemoryActivityRepository;
use Tests\Support\Fakes\RecordingAuditTrail;
use Tests\Support\Fakes\RecordingEventPublisher;
use Tests\Support\Fakes\ThrowingEventPublisher;

const TENANT = 'tenant-thomas';
const ACTIVITY_UUID = '01900000-0000-7000-8000-0000000000aa';

beforeEach(function (): void {
    $this->repository = new InMemoryActivityRepository();
    $this->publisher = new RecordingEventPublisher();
    $this->audit = new RecordingAuditTrail();
    $this->context = new ExecutionContext(TenantId::fromString(TENANT));
    $this->useCase = new RecordActivityUseCase(
        $this->repository,
        new FixedIdGenerator([ACTIVITY_UUID]),
        FixedClock::at('2026-08-21T09:00:00+00:00'),
        $this->publisher,
        $this->audit,
    );
});

/** The real 19/08 test run, expressed as a use-case input. */
function tenKilometreRun(array $overrides = []): RecordActivityInput
{
    $splits = array_map(
        static fn (array $s): SplitInput => new SplitInput($s[0], 1001, $s[1], $s[2]),
        [
            [1, 226, -10], [2, 194, 3], [3, 211, -3], [4, 273, -9], [5, 274, 3],
            [6, 269, 4], [7, 316, -7], [8, 299, 9], [9, 222, 0], [10, 264, 2],
        ],
    );

    return new RecordActivityInput(
        occurredAt: '2026-08-19T18:00:00+00:00',
        source: $overrides['source'] ?? ActivitySource::MANUAL->value,
        distanceMeters: $overrides['distanceMeters'] ?? 10010,
        movingSeconds: $overrides['movingSeconds'] ?? 2555,
        elapsedSeconds: 2653,
        elevationGainMeters: 32,
        splits: $overrides['splits'] ?? $splits,
        bestEfforts: [
            new BestEffortInput('5k', 5000, 1160, true),
            new BestEffortInput('10k', 10000, 2621, true),
        ],
    );
}

describe('Feature: Recording an activity', function (): void {
    it('records a run with its splits and derives the average pace', function (): void {
        $output = $this->useCase->execute(tenKilometreRun(), $this->context);

        expect($output->activityId)->toBe(ACTIVITY_UUID)
            ->and($output->averagePaceSecondsPerKm)->toEqualWithDelta(255.24, 0.1);

        $stored = $this->repository->ofId(
            ActivityId::fromString(ACTIVITY_UUID),
            TenantId::fromString(TENANT),
        );
        expect($stored)->not->toBeNull()
            ->and($stored->version())->toBe(1);
    });

    it('writes the activity.recorded event to the outbox in the same save', function (): void {
        $this->useCase->execute(tenKilometreRun(), $this->context);

        expect($this->repository->outbox)->toHaveCount(1)
            ->and($this->repository->outbox[0])->toBeInstanceOf(ActivityRecorded::class)
            ->and($this->repository->outbox[0]->name())->toBe('activity.recorded');
    });

    it('publishes the recorded event and writes an audit entry', function (): void {
        $this->useCase->execute(tenKilometreRun(), $this->context);

        expect($this->publisher->published)->toHaveCount(1)
            ->and($this->audit->entries)->toHaveCount(1)
            ->and($this->audit->entries[0]['action'])->toBe('activity.recorded')
            ->and($this->audit->entries[0]['tenant'])->toBe(TENANT);
    });

    it('keeps the activity saved even when publishing fails', function (): void {
        $useCase = new RecordActivityUseCase(
            $this->repository,
            new FixedIdGenerator([ACTIVITY_UUID]),
            FixedClock::at('2026-08-21T09:00:00+00:00'),
            new ThrowingEventPublisher(),
            $this->audit,
        );

        try {
            $useCase->execute(tenKilometreRun(), $this->context);
        } catch (RuntimeException) {
            // publishing threw — the save must already have happened
        }

        $stored = $this->repository->ofId(
            ActivityId::fromString(ACTIVITY_UUID),
            TenantId::fromString(TENANT),
        );
        expect($stored)->not->toBeNull()
            ->and($this->repository->outbox)->toHaveCount(1);
    });

    it('rejects a run with zero distance', function (): void {
        $call = fn () => $this->useCase->execute(tenKilometreRun(['distanceMeters' => 0, 'splits' => []]), $this->context);

        expect($call)->toThrow(InvalidActivity::class);

        try {
            $call();
        } catch (InvalidActivity $e) {
            expect($e->errorCode)->toBe(ActivityErrorCode::DISTANCE_MUST_BE_POSITIVE);
        }
    });

    it('rejects a run with zero moving time', function (): void {
        $call = fn () => $this->useCase->execute(tenKilometreRun(['movingSeconds' => 0, 'splits' => []]), $this->context);

        expect($call)->toThrow(InvalidActivity::class);

        try {
            $call();
        } catch (InvalidActivity $e) {
            expect($e->errorCode)->toBe(ActivityErrorCode::DURATION_MUST_BE_POSITIVE);
        }
    });

    it('rejects splits that do not cover the activity distance', function (): void {
        $call = fn () => $this->useCase->execute(tenKilometreRun([
            'splits' => [new SplitInput(1, 2500, 600, 0), new SplitInput(2, 2500, 600, 0)],
        ]), $this->context);

        expect($call)->toThrow(InvalidActivity::class);

        try {
            $call();
        } catch (InvalidActivity $e) {
            expect($e->errorCode)->toBe(ActivityErrorCode::SPLITS_DISTANCE_MISMATCH);
        }
    });

    it('rejects a run that duplicates one already recorded that day', function (): void {
        $this->useCase->execute(tenKilometreRun(), $this->context);

        $call = fn () => $this->useCase->execute(tenKilometreRun(), $this->context);

        expect($call)->toThrow(Cadence\Activity\Domain\Exception\DuplicateActivity::class);
    });

    it('is isolated per tenant: another tenant cannot read the activity', function (): void {
        $this->useCase->execute(tenKilometreRun(), $this->context);

        $seenByOther = $this->repository->ofId(
            ActivityId::fromString(ACTIVITY_UUID),
            TenantId::fromString('tenant-someone-else'),
        );

        expect($seenByOther)->toBeNull();
    });
});
