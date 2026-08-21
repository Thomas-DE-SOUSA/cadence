<?php

declare(strict_types=1);

use Cadence\Athlete\Application\UseCase\SaveProfile\SaveProfileInput;
use Cadence\Athlete\Application\UseCase\SaveProfile\SaveProfileUseCase;
use Cadence\Athlete\Domain\Event\AthleteProfileSaved;
use Cadence\Athlete\Domain\Exception\AthleteErrorCode;
use Cadence\Athlete\Domain\Exception\InvalidAthlete;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\FixedIdGenerator;
use Tests\Support\Fakes\InMemoryAthleteRepository;
use Tests\Support\Fakes\RecordingAuditTrail;
use Tests\Support\Fakes\RecordingEventPublisher;

const ATHLETE_TENANT = 'tenant-coach';
const ATHLETE_ID = '01900000-0000-7000-8000-0000000000cc';

function makeInput(array $overrides = []): SaveProfileInput
{
    $base = [
        'displayName' => 'Thomas',
        'birthDate' => '2000-03-15',
        'heightCm' => 180,
        'weightKg' => 72.0,
        'restingHr' => 48,
        'maxHr' => 190,
        'sessionsPerWeek' => 4,
        'weeklyVolumeKm' => 40,
        'preferredDays' => [1, 3, 5, 7],
        'raceName' => 'Odyssée Paris',
        'raceDate' => '2026-10-04',
        'goalDistanceMeters' => 10000,
        'goalTargetSeconds' => 2400,
        'longTermGoal' => 'Un trail longue distance dans un an.',
    ];
    $data = [...$base, ...$overrides];

    return new SaveProfileInput(
        $data['displayName'],
        $data['birthDate'],
        $data['heightCm'],
        $data['weightKg'],
        $data['restingHr'],
        $data['maxHr'],
        $data['sessionsPerWeek'],
        $data['weeklyVolumeKm'],
        $data['preferredDays'],
        $data['raceName'],
        $data['raceDate'],
        $data['goalDistanceMeters'],
        $data['goalTargetSeconds'],
        $data['longTermGoal'],
    );
}

beforeEach(function (): void {
    $this->repository = new InMemoryAthleteRepository();
    $this->publisher = new RecordingEventPublisher();
    $this->audit = new RecordingAuditTrail();
    $this->context = new ExecutionContext(TenantId::fromString(ATHLETE_TENANT));
    $this->useCase = new SaveProfileUseCase(
        $this->repository,
        new FixedIdGenerator([ATHLETE_ID]),
        FixedClock::at('2026-08-21T09:00:00+00:00'),
        $this->publisher,
        $this->audit,
    );
});

describe('Feature: Saving the athlete profile', function (): void {
    it('creates the profile on first save', function (): void {
        $id = $this->useCase->execute(makeInput(), $this->context);

        expect($id)->toBe(ATHLETE_ID);
        $stored = $this->repository->ofTenant(TenantId::fromString(ATHLETE_TENANT));
        expect($stored)->not->toBeNull()
            ->and($stored->version())->toBe(1)
            ->and($stored->profile()->displayName)->toBe('Thomas')
            ->and($stored->profile()->maxHr)->toBe(190);

        expect($this->publisher->published)->toHaveCount(1)
            ->and($this->publisher->published[0])->toBeInstanceOf(AthleteProfileSaved::class);
        expect($this->audit->entries)->toHaveCount(1)
            ->and($this->audit->entries[0]['action'])->toBe('athlete.profile_saved');
    });

    it('updates in place and bumps the version on the second save', function (): void {
        $this->useCase->execute(makeInput(), $this->context);
        $id = $this->useCase->execute(makeInput(['weightKg' => 70.0, 'sessionsPerWeek' => 5]), $this->context);

        expect($id)->toBe(ATHLETE_ID); // same aggregate, not a new id
        $stored = $this->repository->ofTenant(TenantId::fromString(ATHLETE_TENANT));
        expect($stored->version())->toBe(2)
            ->and($stored->profile()->weightKg)->toBe(70.0)
            ->and($stored->profile()->sessionsPerWeek)->toBe(5);
    });

    it('normalises preferred days (unique + sorted)', function (): void {
        $this->useCase->execute(makeInput(['preferredDays' => [5, 1, 5, 3]]), $this->context);

        $stored = $this->repository->ofTenant(TenantId::fromString(ATHLETE_TENANT));
        expect($stored->profile()->preferredDays)->toBe([1, 3, 5]);
    });

    it('rejects an out-of-range heart rate', function (): void {
        $call = fn () => $this->useCase->execute(makeInput(['maxHr' => 90]), $this->context);

        expect($call)->toThrow(InvalidAthlete::class);
        try {
            $call();
        } catch (InvalidAthlete $e) {
            expect($e->errorCode)->toBe(AthleteErrorCode::INVALID_HEART_RATE);
        }
    });

    it('is isolated per tenant', function (): void {
        $this->useCase->execute(makeInput(), $this->context);

        expect($this->repository->ofTenant(TenantId::fromString('tenant-other')))->toBeNull();
    });
});
