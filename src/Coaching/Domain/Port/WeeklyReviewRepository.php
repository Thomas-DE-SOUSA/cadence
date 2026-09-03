<?php

declare(strict_types=1);

namespace Cadence\Coaching\Domain\Port;

use Cadence\Coaching\Domain\Model\WeeklyReview;
use Cadence\Coaching\Domain\ValueObject\WeeklyReviewId;
use Cadence\Shared\Domain\TenantId;

interface WeeklyReviewRepository
{
    public function save(WeeklyReview $review): void;

    public function ofId(WeeklyReviewId $id, TenantId $tenant): ?WeeklyReview;

    /** The tenant's review thread for a given Mon–Sun week (its start date), or null. */
    public function forWeek(string $weekStart, TenantId $tenant): ?WeeklyReview;
}
