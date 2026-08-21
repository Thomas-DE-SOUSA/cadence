<?php

declare(strict_types=1);

namespace Cadence\Athlete\Domain\Model;

use Cadence\Athlete\Domain\Event\AthleteProfileSaved;
use Cadence\Athlete\Domain\ValueObject\AthleteId;
use Cadence\Athlete\Domain\ValueObject\ProfileData;
use Cadence\Shared\Domain\AggregateRoot;
use Cadence\Shared\Domain\TenantId;
use DateTimeImmutable;

/**
 * The athlete profile aggregate — one per tenant. Holds the editable personal
 * data (identity, physiology, availability, goal, settings) and is versioned
 * for optimistic concurrency.
 *
 * @phpstan-import-type ProfileSnapshot from ProfileData
 *
 * @phpstan-type AthleteSnapshot array{id:string,tenant_id:string,profile:ProfileSnapshot,created_at:string,version:int}
 */
final class Athlete extends AggregateRoot
{
    private function __construct(
        private readonly AthleteId $id,
        private readonly TenantId $tenant,
        private ProfileData $profile,
        private readonly string $createdAt,
        private int $version,
    ) {
    }

    public static function create(AthleteId $id, TenantId $tenant, ProfileData $profile, DateTimeImmutable $recordedAt): self
    {
        $athlete = new self($id, $tenant, $profile, $recordedAt->format(DATE_ATOM), version: 1);
        $athlete->recordEvent(new AthleteProfileSaved($id->value, $recordedAt, $tenant->value));

        return $athlete;
    }

    public function updateProfile(ProfileData $profile, DateTimeImmutable $recordedAt): void
    {
        $this->profile = $profile;
        $this->version++;
        $this->recordEvent(new AthleteProfileSaved($this->id->value, $recordedAt, $this->tenant->value));
    }

    public function id(): AthleteId
    {
        return $this->id;
    }

    public function tenant(): TenantId
    {
        return $this->tenant;
    }

    public function profile(): ProfileData
    {
        return $this->profile;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** @return AthleteSnapshot */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id->value,
            'tenant_id' => $this->tenant->value,
            'profile' => $this->profile->toSnapshot(),
            'created_at' => $this->createdAt,
            'version' => $this->version,
        ];
    }

    /** @param AthleteSnapshot $s */
    public static function fromSnapshot(array $s): self
    {
        return new self(
            AthleteId::fromString($s['id']),
            TenantId::fromString($s['tenant_id']),
            ProfileData::fromSnapshot($s['profile']),
            $s['created_at'],
            $s['version'],
        );
    }
}
