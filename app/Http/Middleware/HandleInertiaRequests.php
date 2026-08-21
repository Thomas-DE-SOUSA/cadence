<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Cadence\Athlete\Domain\Port\AthleteRepository;
use Cadence\Athlete\Infrastructure\Read\TopbarSummary;
use Cadence\Shared\Application\TenantContext;
use Cadence\Shared\Clock\Clock;
use Cadence\Training\Infrastructure\Read\RaceGoalView;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Props shared with every Inertia response.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => ['name' => 'Cadence'],
            'athlete' => fn (): ?array => $this->athleteSummary(),
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
            ],
        ];
    }

    /**
     * @return array{name:string,initial:string,raceName:string|null,raceDaysLeft:int|null}|null
     */
    private function athleteSummary(): ?array
    {
        try {
            $tenant = app(TenantContext::class)->current();

            return TopbarSummary::build(
                app(AthleteRepository::class),
                $tenant,
                RaceGoalView::forTenant($tenant->value),
                app(Clock::class)->now(),
            );
        } catch (Throwable) {
            return null;
        }
    }
}
