<?php

declare(strict_types=1);

use Cadence\Activity\Application\Port\ParsedActivity;
use Cadence\Activity\Application\UseCase\ImportActivity\ImportActivityUseCase;
use Cadence\Activity\Application\UseCase\ImportActivityFromText\ImportActivityFromTextUseCase;
use Cadence\Activity\Application\UseCase\RecordActivity\BestEffortInput;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;
use Cadence\Activity\Domain\Event\ActivityImported;
use Cadence\Shared\Application\ExecutionContext;
use Cadence\Shared\Domain\TenantId;
use Tests\Support\Fakes\FixedClock;
use Tests\Support\Fakes\FixedIdGenerator;
use Tests\Support\Fakes\InMemoryActivityRepository;
use Tests\Support\Fakes\RecordingAuditTrail;
use Tests\Support\Fakes\RecordingEventPublisher;
use Tests\Support\Fakes\StubStravaTextParser;

describe('Feature: Importing an activity from pasted text', function (): void {
    it('parses the text and imports the resulting activity', function (): void {
        $repository = new InMemoryActivityRepository();
        $import = new ImportActivityUseCase(
            $repository,
            new FixedIdGenerator(['01900000-0000-7000-8000-0000000000cc']),
            FixedClock::at('2026-08-21T09:00:00+00:00'),
            new RecordingEventPublisher(),
            new RecordingAuditTrail(),
        );

        $parsed = new ParsedActivity(
            occurredAtIso: '2026-08-19T18:00:00+00:00',
            distanceMeters: 1001,
            movingSeconds: 226,
            elapsedSeconds: 240,
            elevationGainMeters: 0,
            splits: [new SplitInput(1, 1001, 226, -10)],
            bestEfforts: [new BestEffortInput('1 km', 1001, 226, false)],
        );

        $useCase = new ImportActivityFromTextUseCase(new StubStravaTextParser($parsed), $import);

        $output = $useCase->execute(
            'Sortie 10 km, 42:35, splits...',
            new ExecutionContext(TenantId::fromString('tenant-thomas')),
        );

        expect($output->imported)->toBeTrue()
            ->and($output->activityId)->toBe('01900000-0000-7000-8000-0000000000cc')
            ->and($repository->outbox)->toHaveCount(1)
            ->and($repository->outbox[0])->toBeInstanceOf(ActivityImported::class);
    });
});
