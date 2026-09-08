<?php

declare(strict_types=1);

namespace Cadence\Coaching\Infrastructure\Http\Controller;

use Cadence\Coaching\Domain\Model\Message;
use Cadence\Coaching\Domain\Port\WeeklyReviewRepository;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Returns the weekly review thread as JSON so the page can reload it after streaming. */
final class ShowWeeklyThreadController
{
    public function __construct(
        private readonly WeeklyReviewRepository $reviews,
        private readonly TenantContext $tenantContext,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $weekStart = ($start = (string) $request->query('week_start', '')) !== ''
            ? (new \DateTimeImmutable($start))->modify('monday this week')->format('Y-m-d')
            : $this->clock->now()->modify('monday this week')->format('Y-m-d');

        $review = $this->reviews->forWeek($weekStart, $tenant);

        return new JsonResponse([
            'thread' => $review === null ? [] : array_map(
                static fn (Message $m): array => ['role' => $m->role->value, 'text' => $m->text],
                $review->messages(),
            ),
        ]);
    }
}
