<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Cadence\Athlete\Domain\Model\Athlete;
use Cadence\Athlete\Domain\Port\AthleteRepository;
use Cadence\Shared\Domain\DomainEvent;
use Cadence\Shared\Domain\TenantId;

final class InMemoryAthleteRepository implements AthleteRepository
{
    /** @var array<string, Athlete> keyed by tenant id */
    private array $store = [];

    /** @var list<DomainEvent> */
    public array $outbox = [];

    public function save(Athlete $athlete, array $events): void
    {
        $this->store[$athlete->tenant()->value] = $athlete;

        foreach ($events as $event) {
            $this->outbox[] = $event;
        }
    }

    public function ofTenant(TenantId $tenant): ?Athlete
    {
        return $this->store[$tenant->value] ?? null;
    }
}
