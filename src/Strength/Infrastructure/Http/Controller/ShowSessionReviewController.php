<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Domain\Port\StrengthSessionRepository;
use Cadence\Strength\Domain\Service\SessionProgressionComparator;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Inertia\Inertia;
use Inertia\Response;

/** The post-session recap: what beat last time, shown after a session is completed. */
final class ShowSessionReviewController
{
    public function __construct(
        private readonly StrengthSessionRepository $sessions,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $tenant = $this->tenantContext->current();

        $session = $this->sessions->ofId($id, $tenant);
        if ($session === null) {
            abort(404);
        }

        // Reference set = earlier done sessions (this one excluded). forTenant is
        // already ordered most-recent-first, and we only keep the ones on or
        // before this session's date so we compare against the *previous* time.
        $date = $session->toSnapshot()['date'];
        $priorDone = array_values(array_filter(
            $this->sessions->forTenant($tenant),
            static fn ($s): bool => $s->status()->isDone()
                && $s->id() !== $id
                && $s->toSnapshot()['date'] <= $date,
        ));

        return Inertia::render(
            'StrengthSessionReview',
            StrengthView::sessionReview($session, $priorDone, new SessionProgressionComparator()) + [
                'backUrl' => route('strength'),
            ],
        );
    }
}
