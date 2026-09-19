<?php

declare(strict_types=1);

namespace Cadence\Strength\Infrastructure\Http\Controller;

use Cadence\Shared\Application\TenantContext;
use Cadence\Strength\Domain\Port\StrengthProfileRepository;
use Cadence\Strength\Infrastructure\Read\StrengthView;
use Inertia\Inertia;
use Inertia\Response;

final class ShowStrengthProfileController
{
    public function __construct(
        private readonly StrengthProfileRepository $profiles,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(): Response
    {
        return Inertia::render('StrengthProfile', [
            'profile' => StrengthView::profile($this->profiles->forTenant($this->tenantContext->current())),
            'options' => StrengthView::profileOptions(),
        ]);
    }
}
