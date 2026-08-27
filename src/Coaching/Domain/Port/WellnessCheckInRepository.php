<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\ValueObject\WellnessCheckIn;
use Cadence\Shared\Domain\TenantId;

/** Persists the athlete's daily subjective check-ins (one per day, per tenant). */
interface WellnessCheckInRepository
{
    public function save(TenantId $tenant, WellnessCheckIn $checkIn): void;

    public function forDate(TenantId $tenant, string $date): ?WellnessCheckIn;

    public function latestFor(TenantId $tenant): ?WellnessCheckIn;

    /**
     * Check-ins on or after {@see $since} (Y-m-d), most recent first.
     *
     * @return list<WellnessCheckIn>
     */
    public function since(TenantId $tenant, string $since): array;
}
