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
            'auth' => ['user' => $this->authUser($request)],
            'topbar' => fn (): ?array => $this->athleteSummary(),
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
            ],
        ];
    }

    /**
     * @return array{name:string,email:string,initial:string}|null
     */
    private function authUser(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $name = trim((string) $user->name);

        return [
            'name' => $name,
            'email' => (string) $user->email,
            'initial' => mb_strtoupper(mb_substr($name !== '' ? $name : '?', 0, 1)),
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
