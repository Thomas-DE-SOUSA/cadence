<?php

declare(strict_types=1);

namespace Cadence\Activity\Domain\Model;

use Cadence\Activity\Domain\Enum\ActivitySource;
use Cadence\Activity\Domain\Event\ActivityImported;
use Cadence\Activity\Domain\Event\ActivityRecorded;
use Cadence\Activity\Domain\Event\ActivityRevised;
use Cadence\Activity\Domain\Exception\ActivityErrorCode;
use Cadence\Activity\Domain\Exception\InvalidActivity;
use Cadence\Activity\Domain\ValueObject\ActivityId;
use Cadence\Activity\Domain\ValueObject\Distance;
use Cadence\Activity\Domain\ValueObject\Duration;
use Cadence\Activity\Domain\ValueObject\Elevation;
use Cadence\Activity\Domain\ValueObject\Pace;
use Cadence\Shared\Domain\AggregateRoot;
use Cadence\Shared\Domain\TenantId;
use DateTimeImmutable;

/**
 * @phpstan-type SplitRow array{index:int,distance_meters:int,duration_seconds:int,elevation_meters:int}
 * @phpstan-type BestEffortRow array{label:string,distance_meters:int,duration_seconds:int,is_personal_record:bool}
 * @phpstan-type ActivitySnapshot array{id:string,tenant_id:string,occurred_at:string,source:string,external_id:string|null,distance_meters:int,moving_seconds:int,elapsed_seconds:int,elevation_gain_meters:int,average_pace_seconds_per_km:float,splits:list<SplitRow>,best_efforts:list<BestEffortRow>,version:int}
 */
final class Activity extends AggregateRoot
{
    /** Splits are accepted when their summed distance is within this fraction of the activity distance. */
    private const SPLIT_DISTANCE_TOLERANCE = 0.05;

    /**
     * @param list<Split> $splits
     * @param list<BestEffort> $bestEfforts
     */
    private function __construct(
        private readonly ActivityId $id,
        private readonly TenantId $tenant,
        private readonly DateTimeImmutable $occurredAt,
        private readonly ActivitySource $source,
        private readonly ?string $externalId,
        private readonly Distance $distance,
        private readonly Duration $movingTime,
        private readonly Duration $elapsedTime,
        private readonly Elevation $elevationGain,
        private readonly array $splits,
        private readonly array $bestEfforts,
        private readonly int $version,
    ) {
    }

    /**
     * Records a manually-entered run.
     *
     * @param list<Split> $splits
     * @param list<BestEffort> $bestEfforts
     */
    public static function record(
        ActivityId $id,
        TenantId $tenant,
        DateTimeImmutable $occurredAt,
        ActivitySource $source,
        Distance $distance,
        Duration $movingTime,
        Duration $elapsedTime,
        Elevation $elevationGain,
        array $splits,
        array $bestEfforts,
        DateTimeImmutable $recordedAt,
    ): self {
        $activity = self::assemble(
            $id, $tenant, $occurredAt, $source, null,
            $distance, $movingTime, $elapsedTime, $elevationGain, $splits, $bestEfforts,
        );

        $activity->recordEvent(new ActivityRecorded(
            $id->value,
            $recordedAt,
            $tenant->value,
            $distance->meters,
            $movingTime->seconds,
            $activity->averagePace()->secondsPerKm,
            $source->value,
        ));

        return $activity;
    }

    /**
     * Imports a run from an external provider (e.g. Strava), identified by its external id.
     *
     * @param list<Split> $splits
     * @param list<BestEffort> $bestEfforts
     */
    public static function import(
        ActivityId $id,
        TenantId $tenant,
        DateTimeImmutable $occurredAt,
        ActivitySource $source,
        string $externalId,
        Distance $distance,
        Duration $movingTime,
        Duration $elapsedTime,
        Elevation $elevationGain,
        array $splits,
        array $bestEfforts,
        DateTimeImmutable $recordedAt,
    ): self {
        $activity = self::assemble(
            $id, $tenant, $occurredAt, $source, $externalId,
            $distance, $movingTime, $elapsedTime, $elevationGain, $splits, $bestEfforts,
        );

        $activity->recordEvent(new ActivityImported(
            $id->value,
            $recordedAt,
            $tenant->value,
            $distance->meters,
            $movingTime->seconds,
            $activity->averagePace()->secondsPerKm,
            $source->value,
            $externalId,
        ));

        return $activity;
    }

    /**
     * Revises the summary fields (date, distance, times, elevation), keeping splits
     * and best efforts. Bumps the version and records the revision.
     */
    public function revise(
        DateTimeImmutable $occurredAt,
        Distance $distance,
        Duration $movingTime,
        Duration $elapsedTime,
        Elevation $elevationGain,
        DateTimeImmutable $recordedAt,
    ): self {
        self::guardSplitsCoverDistance($distance, $this->splits);

        $revised = new self(
            $this->id,
            $this->tenant,
            $occurredAt,
            $this->source,
            $this->externalId,
            $distance,
            $movingTime,
            $elapsedTime,
            $elevationGain,
            $this->splits,
            $this->bestEfforts,
            $this->version + 1,
        );

        $revised->recordEvent(new ActivityRevised(
            $this->id->value,
            $recordedAt,
            $this->tenant->value,
            $distance->meters,
            $movingTime->seconds,
            $revised->averagePace()->secondsPerKm,
            $this->source->value,
        ));

        return $revised;
    }

    /**
     * @param list<Split> $splits
     * @param list<BestEffort> $bestEfforts
     */
    private static function assemble(
        ActivityId $id,
        TenantId $tenant,
        DateTimeImmutable $occurredAt,
        ActivitySource $source,
        ?string $externalId,
        Distance $distance,
        Duration $movingTime,
        Duration $elapsedTime,
        Elevation $elevationGain,
        array $splits,
        array $bestEfforts,
    ): self {
        self::guardSplitsCoverDistance($distance, $splits);

        return new self(
            $id, $tenant, $occurredAt, $source, $externalId,
            $distance, $movingTime, $elapsedTime, $elevationGain, $splits, $bestEfforts,
            version: 1,
        );
    }

    /** @param list<Split> $splits */
    private static function guardSplitsCoverDistance(Distance $distance, array $splits): void
    {
        if ($splits === []) {
            return;
        }

        $summed = array_sum(array_map(static fn (Split $s): int => $s->distance->meters, $splits));
        $delta = abs($summed - $distance->meters);

        if ($delta > $distance->meters * self::SPLIT_DISTANCE_TOLERANCE) {
            throw new InvalidActivity(
                ActivityErrorCode::SPLITS_DISTANCE_MISMATCH,
                "Splits sum to {$summed} m but the activity distance is {$distance->meters} m.",
            );
        }
    }

    public function averagePace(): Pace
    {
        return Pace::fromDistanceAndDuration($this->distance, $this->movingTime);
    }

    public function id(): ActivityId
    {
        return $this->id;
    }

    public function tenant(): TenantId
    {
        return $this->tenant;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** @return ActivitySnapshot */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id->value,
            'tenant_id' => $this->tenant->value,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'source' => $this->source->value,
            'external_id' => $this->externalId,
            'distance_meters' => $this->distance->meters,
            'moving_seconds' => $this->movingTime->seconds,
            'elapsed_seconds' => $this->elapsedTime->seconds,
            'elevation_gain_meters' => $this->elevationGain->meters,
            'average_pace_seconds_per_km' => $this->averagePace()->secondsPerKm,
            'splits' => array_map(static fn (Split $s): array => $s->toSnapshot(), $this->splits),
            'best_efforts' => array_map(static fn (BestEffort $b): array => $b->toSnapshot(), $this->bestEfforts),
            'version' => $this->version,
        ];
    }

    /** @param ActivitySnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            ActivityId::fromString($s['id']),
            TenantId::fromString($s['tenant_id']),
            new DateTimeImmutable($s['occurred_at']),
            ActivitySource::from($s['source']),
            $s['external_id'],
            Distance::fromStorage($s['distance_meters']),
            Duration::fromStorage($s['moving_seconds']),
            Duration::fromStorage($s['elapsed_seconds']),
            Elevation::ofMeters($s['elevation_gain_meters']),
            array_map(static fn (array $row): Split => Split::fromSnapshot($row), $s['splits']),
            array_map(static fn (array $row): BestEffort => BestEffort::fromSnapshot($row), $s['best_efforts']),
            $s['version'],
        );
    }
}
