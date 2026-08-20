<?php

declare(strict_types=1);

namespace Database\Seeders;

use Cadence\Activity\Application\UseCase\RecordActivity\BestEffortInput;
use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityInput;
use Cadence\Activity\Application\UseCase\RecordActivity\RecordActivityUseCase;
use Cadence\Activity\Application\UseCase\RecordActivity\SplitInput;
use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Infrastructure\Persistence\Eloquent\ActivityModel;
use Illuminate\Database\Seeder;

/**
 * Seeds Thomas' real 19/08/2026 test run through the RecordActivity use case,
 * so the seed exercises the exact production path (aggregate + outbox + audit).
 */
final class ActivitySeeder extends Seeder
{
    private const TENANT = 'tenant-thomas';

    public function run(): void
    {
        if (ActivityModel::query()->where('tenant_id', self::TENANT)->exists()) {
            return;
        }

        /** @var list<array{int, int, int}> $rawSplits [index, seconds, elevation] */
        $rawSplits = [
            [1, 226, -10], [2, 194, 3], [3, 211, -3], [4, 273, -9], [5, 274, 3],
            [6, 269, 4], [7, 316, -7], [8, 299, 9], [9, 222, 0], [10, 264, 2],
        ];

        $splits = array_map(
            static fn (array $s): SplitInput => new SplitInput($s[0], 1001, $s[1], $s[2]),
            $rawSplits,
        );

        $input = new RecordActivityInput(
            tenantId: self::TENANT,
            occurredAt: '2026-08-19T18:00:00+00:00',
            source: ActivitySource::MANUAL->value,
            distanceMeters: 10010,
            movingSeconds: 2555,
            elapsedSeconds: 2653,
            elevationGainMeters: 32,
            splits: $splits,
            bestEfforts: [
                new BestEffortInput('2 miles', 3219, 690, true),
                new BestEffortInput('5k', 5000, 1160, true),
                new BestEffortInput('10k', 10000, 2621, true),
            ],
        );

        app(RecordActivityUseCase::class)->execute($input);
    }
}
